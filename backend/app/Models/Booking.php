<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * Booking Model for Car Rental Platform
 * 
 * Manages rental bookings with status tracking, payments, and trip management
 */
class Booking extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'vehicle_id',
        'agency_id',
        'driver_id',
        'booking_number',
        'start_date',
        'end_date',
        'pickup_location',
        'dropoff_location',
        'pickup_latitude',
        'pickup_longitude',
        'dropoff_latitude',
        'dropoff_longitude',
        'total_amount',
        'security_deposit',
        'commission_amount',
        'discount_amount',
        'tax_amount',
        'status',
        'payment_status',
        'notes',
        'special_requests',
        'estimated_distance',
        'actual_distance',
        'fuel_level_start',
        'fuel_level_end',
        'damage_report',
        'pickup_time',
        'dropoff_time',
        'cancellation_reason',
        'cancelled_at',
        'confirmed_at',
        'completed_at'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'pickup_time' => 'datetime',
        'dropoff_time' => 'datetime',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'security_deposit' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'pickup_latitude' => 'decimal:8',
        'pickup_longitude' => 'decimal:8',
        'dropoff_latitude' => 'decimal:8',
        'dropoff_longitude' => 'decimal:8',
        'estimated_distance' => 'decimal:2',
        'actual_distance' => 'decimal:2',
        'fuel_level_start' => 'integer',
        'fuel_level_end' => 'integer',
        'damage_report' => 'array',
        'special_requests' => 'array'
    ];

    /**
     * Booking status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_NO_SHOW = 'no_show';

    /**
     * Payment status constants
     */
    const PAYMENT_PENDING = 'pending';
    const PAYMENT_PARTIAL = 'partial';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_REFUNDED = 'refunded';
    const PAYMENT_FAILED = 'failed';

    /**
     * Get the user who made this booking
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the vehicle for this booking
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the agency that owns the vehicle
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * Get the assigned driver
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get all payments for this booking
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the primary payment for this booking
     */
    public function primaryPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->where('is_primary', true);
    }

    /**
     * Get GPS tracking records for this booking
     */
    public function gpsTrackings(): HasMany
    {
        return $this->hasMany(GpsTracking::class);
    }

    /**
     * Get reviews for this booking
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get booking notifications
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(BookingNotification::class);
    }

    /**
     * Generate unique booking number
     */
    public static function generateBookingNumber(): string
    {
        do {
            $number = 'BK' . date('Y') . str_pad(random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('booking_number', $number)->exists());

        return $number;
    }

    /**
     * Get booking duration in days
     */
    public function getDurationInDays(): int
    {
        return $this->start_date->diffInDays($this->end_date) ?: 1;
    }

    /**
     * Get booking duration in hours
     */
    public function getDurationInHours(): int
    {
        return $this->start_date->diffInHours($this->end_date) ?: 1;
    }

    /**
     * Calculate total amount based on vehicle rates and duration
     */
    public function calculateTotalAmount(): float
    {
        if (!$this->vehicle) {
            return 0;
        }

        $days = $this->getDurationInDays();
        $baseAmount = $days * $this->vehicle->daily_rate;

        // Apply any discounts
        $discountAmount = $this->discount_amount ?? 0;

        // Calculate tax (assuming 10% tax rate)
        $taxRate = config('rental.tax_rate', 0.10);
        $subtotal = $baseAmount - $discountAmount;
        $taxAmount = $subtotal * $taxRate;

        // Calculate commission (3% platform fee)
        $commissionRate = config('rental.commission_rate', 0.03);
        $commissionAmount = $subtotal * $commissionRate;

        $total = $subtotal + $taxAmount;

        // Update the calculated amounts
        $this->update([
            'tax_amount' => $taxAmount,
            'commission_amount' => $commissionAmount,
            'total_amount' => $total
        ]);

        return $total;
    }

    /**
     * Check if booking can be cancelled
     */
    public function canBeCancelled(): bool
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
            return false;
        }

        // Check if within cancellation window (24 hours before start)
        $cancellationDeadline = $this->start_date->subHours(24);
        return now()->lt($cancellationDeadline);
    }

    /**
     * Check if booking can be modified
     */
    public function canBeModified(): bool
    {
        if (in_array($this->status, [self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
            return false;
        }

        // Check if within modification window (4 hours before start)
        $modificationDeadline = $this->start_date->subHours(4);
        return now()->lt($modificationDeadline);
    }

    /**
     * Check if booking is active (in progress)
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Check if booking is upcoming
     */
    public function isUpcoming(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED]) &&
               $this->start_date->isFuture();
    }

    /**
     * Check if booking is overdue (past end date but not completed)
     */
    public function isOverdue(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_IN_PROGRESS]) &&
               $this->end_date->isPast();
    }

    /**
     * Confirm the booking
     */
    public function confirm(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_CONFIRMED,
            'confirmed_at' => now()
        ]);
    }

    /**
     * Start the trip
     */
    public function startTrip(array $data = []): bool
    {
        if ($this->status !== self::STATUS_CONFIRMED) {
            return false;
        }

        $updateData = [
            'status' => self::STATUS_IN_PROGRESS,
            'pickup_time' => now()
        ];

        // Add optional data like fuel level, mileage, etc.
        if (isset($data['fuel_level_start'])) {
            $updateData['fuel_level_start'] = $data['fuel_level_start'];
        }

        if (isset($data['actual_pickup_location'])) {
            $updateData['pickup_location'] = $data['actual_pickup_location'];
        }

        return $this->update($updateData);
    }

    /**
     * Complete the booking
     */
    public function complete(array $data = []): bool
    {
        if ($this->status !== self::STATUS_IN_PROGRESS) {
            return false;
        }

        $updateData = [
            'status' => self::STATUS_COMPLETED,
            'dropoff_time' => now(),
            'completed_at' => now()
        ];

        // Add completion data
        if (isset($data['fuel_level_end'])) {
            $updateData['fuel_level_end'] = $data['fuel_level_end'];
        }

        if (isset($data['actual_distance'])) {
            $updateData['actual_distance'] = $data['actual_distance'];
        }

        if (isset($data['damage_report'])) {
            $updateData['damage_report'] = $data['damage_report'];
        }

        $result = $this->update($updateData);

        if ($result) {
            // Update vehicle's booking count and rating
            $this->vehicle->updateBookingsCount();
            $this->vehicle->updateAverageRating();
        }

        return $result;
    }

    /**
     * Cancel the booking
     */
    public function cancel(string $reason, bool $refund = false): bool
    {
        if (!$this->canBeCancelled()) {
            return false;
        }

        $result = $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancellation_reason' => $reason,
            'cancelled_at' => now()
        ]);

        if ($result && $refund) {
            // Process refund logic here
            $this->processRefund();
        }

        return $result;
    }

    /**
     * Process refund for cancelled booking
     */
    public function processRefund(): void
    {
        $primaryPayment = $this->primaryPayment;
        
        if ($primaryPayment && $primaryPayment->status === Payment::STATUS_COMPLETED) {
            // Calculate refund amount based on cancellation policy
            $refundAmount = $this->calculateRefundAmount();
            
            if ($refundAmount > 0) {
                // Create refund payment record
                Payment::create([
                    'booking_id' => $this->id,
                    'user_id' => $this->user_id,
                    'amount' => -$refundAmount, // Negative amount for refund
                    'type' => Payment::TYPE_REFUND,
                    'status' => Payment::STATUS_PENDING,
                    'payment_method' => $primaryPayment->payment_method,
                    'transaction_id' => 'REFUND_' . $primaryPayment->transaction_id
                ]);
            }
        }
    }

    /**
     * Calculate refund amount based on cancellation policy
     */
    public function calculateRefundAmount(): float
    {
        $hoursBeforeStart = now()->diffInHours($this->start_date);
        
        // Cancellation policy: 
        // - 24+ hours: 100% refund
        // - 12-24 hours: 50% refund
        // - Less than 12 hours: No refund
        
        if ($hoursBeforeStart >= 24) {
            return $this->total_amount;
        } elseif ($hoursBeforeStart >= 12) {
            return $this->total_amount * 0.5;
        }
        
        return 0;
    }

    /**
     * Assign driver to booking
     */
    public function assignDriver(int $driverId): bool
    {
        $driver = User::find($driverId);
        
        if (!$driver || !$driver->isDriver() || !$driver->isDriverAvailable()) {
            return false;
        }

        return $this->update(['driver_id' => $driverId]);
    }

    /**
     * Get estimated trip distance
     */
    public function calculateEstimatedDistance(): float
    {
        if (!$this->pickup_latitude || !$this->pickup_longitude || 
            !$this->dropoff_latitude || !$this->dropoff_longitude) {
            return 0;
        }

        // Haversine formula for distance calculation
        $earthRadius = 6371; // km

        $deltaLat = deg2rad($this->dropoff_latitude - $this->pickup_latitude);
        $deltaLng = deg2rad($this->dropoff_longitude - $this->pickup_longitude);

        $a = sin($deltaLat/2) * sin($deltaLat/2) +
             cos(deg2rad($this->pickup_latitude)) * cos(deg2rad($this->dropoff_latitude)) *
             sin($deltaLng/2) * sin($deltaLng/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    /**
     * Get booking status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_CONFIRMED => 'blue',
            self::STATUS_IN_PROGRESS => 'green',
            self::STATUS_COMPLETED => 'gray',
            self::STATUS_CANCELLED => 'red',
            self::STATUS_NO_SHOW => 'purple',
            default => 'gray'
        };
    }

    /**
     * Get human readable status
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending Confirmation',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_NO_SHOW => 'No Show',
            default => 'Unknown'
        };
    }

    /**
     * Scope for bookings by status
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for active bookings
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_CONFIRMED, self::STATUS_IN_PROGRESS]);
    }

    /**
     * Scope for upcoming bookings
     */
    public function scopeUpcoming($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED])
                    ->where('start_date', '>', now());
    }

    /**
     * Scope for overdue bookings
     */
    public function scopeOverdue($query)
    {
        return $query->whereIn('status', [self::STATUS_CONFIRMED, self::STATUS_IN_PROGRESS])
                    ->where('end_date', '<', now());
    }

    /**
     * Scope for bookings in date range
     */
    public function scopeInDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('start_date', [$startDate, $endDate]);
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($booking) {
            // Generate booking number if not set
            if (empty($booking->booking_number)) {
                $booking->booking_number = self::generateBookingNumber();
            }

            // Set default status
            if (empty($booking->status)) {
                $booking->status = self::STATUS_PENDING;
            }

            // Set default payment status
            if (empty($booking->payment_status)) {
                $booking->payment_status = self::PAYMENT_PENDING;
            }

            // Calculate estimated distance
            if (!$booking->estimated_distance) {
                $booking->estimated_distance = $booking->calculateEstimatedDistance();
            }
        });

        static::updating(function ($booking) {
            // Update driver status when booking status changes
            if ($booking->isDirty('status') && $booking->driver_id) {
                $driver = $booking->driver;
                if ($driver) {
                    if ($booking->status === self::STATUS_IN_PROGRESS) {
                        $driver->setDriverStatus(User::DRIVER_STATUS_ON_TRIP);
                    } elseif (in_array($booking->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
                        $driver->setDriverStatus(User::DRIVER_STATUS_AVAILABLE);
                    }
                }
            }
        });
    }
}