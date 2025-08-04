<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Payment Model for Car Rental Platform
 * 
 * Handles all payment transactions, refunds, and commission tracking
 */
class Payment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'booking_id',
        'user_id',
        'agency_id',
        'amount',
        'commission_amount',
        'net_amount',
        'currency',
        'payment_method',
        'payment_gateway',
        'transaction_id',
        'gateway_transaction_id',
        'gateway_payment_id',
        'status',
        'type',
        'description',
        'gateway_response',
        'failure_reason',
        'processed_at',
        'refunded_at',
        'refund_amount',
        'is_primary',
        'metadata'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'gateway_response' => 'array',
        'metadata' => 'array',
        'is_primary' => 'boolean'
    ];

    /**
     * Payment status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    /**
     * Payment type constants
     */
    const TYPE_PAYMENT = 'payment';
    const TYPE_DEPOSIT = 'deposit';
    const TYPE_REFUND = 'refund';
    const TYPE_COMMISSION = 'commission';
    const TYPE_PENALTY = 'penalty';

    /**
     * Payment method constants
     */
    const METHOD_CREDIT_CARD = 'credit_card';
    const METHOD_DEBIT_CARD = 'debit_card';
    const METHOD_PAYPAL = 'paypal';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_CASH = 'cash';
    const METHOD_WALLET = 'wallet';

    /**
     * Payment gateway constants
     */
    const GATEWAY_STRIPE = 'stripe';
    const GATEWAY_PAYPAL = 'paypal';
    const GATEWAY_BANK = 'bank';
    const GATEWAY_MANUAL = 'manual';

    /**
     * Get the booking this payment belongs to
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Get the user who made this payment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the agency that will receive this payment
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * Generate unique transaction ID
     */
    public static function generateTransactionId(): string
    {
        do {
            $id = 'TXN' . date('Ymd') . str_pad(random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('transaction_id', $id)->exists());

        return $id;
    }

    /**
     * Calculate commission and net amounts
     */
    public function calculateAmounts(): void
    {
        if ($this->type === self::TYPE_PAYMENT) {
            // Calculate platform commission (3% default)
            $commissionRate = config('payment.commission_rate', 0.03);
            $this->commission_amount = $this->amount * $commissionRate;
            $this->net_amount = $this->amount - $this->commission_amount;
        } else {
            // For refunds, deposits, etc., no commission
            $this->commission_amount = 0;
            $this->net_amount = $this->amount;
        }
    }

    /**
     * Mark payment as completed
     */
    public function markAsCompleted(array $gatewayData = []): bool
    {
        $updateData = [
            'status' => self::STATUS_COMPLETED,
            'processed_at' => now()
        ];

        if (!empty($gatewayData)) {
            $updateData['gateway_response'] = $gatewayData;
            
            if (isset($gatewayData['transaction_id'])) {
                $updateData['gateway_transaction_id'] = $gatewayData['transaction_id'];
            }
            
            if (isset($gatewayData['payment_id'])) {
                $updateData['gateway_payment_id'] = $gatewayData['payment_id'];
            }
        }

        $result = $this->update($updateData);

        if ($result) {
            // Update booking payment status
            $this->updateBookingPaymentStatus();
        }

        return $result;
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed(string $reason = null, array $gatewayData = []): bool
    {
        $updateData = [
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason
        ];

        if (!empty($gatewayData)) {
            $updateData['gateway_response'] = $gatewayData;
        }

        $result = $this->update($updateData);

        if ($result) {
            // Update booking payment status
            $this->updateBookingPaymentStatus();
        }

        return $result;
    }

    /**
     * Process refund for this payment
     */
    public function processRefund(float $amount = null): bool
    {
        if ($this->status !== self::STATUS_COMPLETED) {
            return false;
        }

        $refundAmount = $amount ?? $this->amount;
        
        if ($refundAmount > $this->amount) {
            return false;
        }

        // Create refund payment record
        $refundPayment = self::create([
            'booking_id' => $this->booking_id,
            'user_id' => $this->user_id,
            'agency_id' => $this->agency_id,
            'amount' => -$refundAmount,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'payment_gateway' => $this->payment_gateway,
            'transaction_id' => self::generateTransactionId(),
            'status' => self::STATUS_PENDING,
            'type' => self::TYPE_REFUND,
            'description' => "Refund for payment {$this->transaction_id}",
            'metadata' => [
                'original_payment_id' => $this->id,
                'refund_amount' => $refundAmount
            ]
        ]);

        // Update original payment status
        $newStatus = ($refundAmount >= $this->amount) 
            ? self::STATUS_REFUNDED 
            : self::STATUS_PARTIALLY_REFUNDED;

        $this->update([
            'status' => $newStatus,
            'refund_amount' => ($this->refund_amount ?? 0) + $refundAmount,
            'refunded_at' => now()
        ]);

        return true;
    }

    /**
     * Update booking payment status based on payment records
     */
    public function updateBookingPaymentStatus(): void
    {
        if (!$this->booking) {
            return;
        }

        $totalPaid = $this->booking->payments()
                        ->where('status', self::STATUS_COMPLETED)
                        ->where('type', self::TYPE_PAYMENT)
                        ->sum('amount');

        $totalRefunded = abs($this->booking->payments()
                            ->where('status', self::STATUS_COMPLETED)
                            ->where('type', self::TYPE_REFUND)
                            ->sum('amount'));

        $netAmount = $totalPaid - $totalRefunded;
        $bookingTotal = $this->booking->total_amount;

        if ($netAmount <= 0) {
            $paymentStatus = Booking::PAYMENT_REFUNDED;
        } elseif ($netAmount >= $bookingTotal) {
            $paymentStatus = Booking::PAYMENT_PAID;
        } elseif ($netAmount > 0) {
            $paymentStatus = Booking::PAYMENT_PARTIAL;
        } else {
            $paymentStatus = Booking::PAYMENT_PENDING;
        }

        $this->booking->update(['payment_status' => $paymentStatus]);
    }

    /**
     * Check if payment can be refunded
     */
    public function canBeRefunded(): bool
    {
        return $this->status === self::STATUS_COMPLETED && 
               $this->type === self::TYPE_PAYMENT &&
               ($this->refund_amount ?? 0) < $this->amount;
    }

    /**
     * Get remaining refundable amount
     */
    public function getRefundableAmount(): float
    {
        if (!$this->canBeRefunded()) {
            return 0;
        }

        return $this->amount - ($this->refund_amount ?? 0);
    }

    /**
     * Get payment method display name
     */
    public function getPaymentMethodDisplayAttribute(): string
    {
        return match($this->payment_method) {
            self::METHOD_CREDIT_CARD => 'Credit Card',
            self::METHOD_DEBIT_CARD => 'Debit Card',
            self::METHOD_PAYPAL => 'PayPal',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::METHOD_CASH => 'Cash',
            self::METHOD_WALLET => 'Digital Wallet',
            default => 'Unknown'
        };
    }

    /**
     * Get payment status display name
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded',
            self::STATUS_PARTIALLY_REFUNDED => 'Partially Refunded',
            default => 'Unknown'
        };
    }

    /**
     * Get payment status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_PROCESSING => 'blue',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_FAILED => 'red',
            self::STATUS_CANCELLED => 'gray',
            self::STATUS_REFUNDED => 'purple',
            self::STATUS_PARTIALLY_REFUNDED => 'orange',
            default => 'gray'
        };
    }

    /**
     * Check if payment is successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if payment failed
     */
    public function isFailed(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_CANCELLED]);
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * Get formatted amount with currency
     */
    public function getFormattedAmountAttribute(): string
    {
        $symbol = match($this->currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => '$'
        };

        return $symbol . number_format(abs($this->amount), 2);
    }

    /**
     * Scope for successful payments
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed payments
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', [self::STATUS_FAILED, self::STATUS_CANCELLED]);
    }

    /**
     * Scope for pending payments
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * Scope for payments by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for payments by method
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope for payments by gateway
     */
    public function scopeByGateway($query, string $gateway)
    {
        return $query->where('payment_gateway', $gateway);
    }

    /**
     * Scope for payments in date range
     */
    public function scopeInDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            // Generate transaction ID if not set
            if (empty($payment->transaction_id)) {
                $payment->transaction_id = self::generateTransactionId();
            }

            // Set default currency
            if (empty($payment->currency)) {
                $payment->currency = config('payment.default_currency', 'USD');
            }

            // Set default status
            if (empty($payment->status)) {
                $payment->status = self::STATUS_PENDING;
            }

            // Set default type
            if (empty($payment->type)) {
                $payment->type = self::TYPE_PAYMENT;
            }

            // Calculate amounts
            $payment->calculateAmounts();
        });

        static::updated(function ($payment) {
            // Update booking payment status when payment status changes
            if ($payment->isDirty('status')) {
                $payment->updateBookingPaymentStatus();
            }
        });
    }
}