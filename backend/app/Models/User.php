<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * User Model for Car Rental Platform
 * 
 * Supports multi-role system: admin, agency, user, driver
 * Includes profile management, authentication, and relationships
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'date_of_birth',
        'license_number',
        'license_expiry',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'profile_photo',
        'is_verified',
        'is_active',
        'agency_id',
        'driver_status',
        'last_login_at',
        'preferences',
        'emergency_contact_name',
        'emergency_contact_phone'
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'date_of_birth' => 'date',
        'license_expiry' => 'date',
        'last_login_at' => 'datetime',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'preferences' => 'array',
        'password' => 'hashed',
    ];

    /**
     * User role constants
     */
    const ROLE_ADMIN = 'admin';
    const ROLE_AGENCY = 'agency';
    const ROLE_USER = 'user';
    const ROLE_DRIVER = 'driver';

    /**
     * Driver status constants
     */
    const DRIVER_STATUS_AVAILABLE = 'available';
    const DRIVER_STATUS_BUSY = 'busy';
    const DRIVER_STATUS_OFFLINE = 'offline';
    const DRIVER_STATUS_ON_TRIP = 'on_trip';

    /**
     * Get the agency that the user belongs to (for drivers)
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * Get all bookings made by this user
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get all bookings assigned to this driver
     */
    public function driverBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'driver_id');
    }

    /**
     * Get all reviews written by this user
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get all support tickets created by this user
     */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * Get all payments made by this user
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get GPS tracking records for this driver
     */
    public function gpsTrackings(): HasMany
    {
        return $this->hasMany(GpsTracking::class, 'driver_id');
    }

    /**
     * Get the owned agency (if user is an agency owner)
     */
    public function ownedAgency(): HasMany
    {
        return $this->hasMany(Agency::class, 'owner_id');
    }

    /**
     * Check if user has admin role
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    /**
     * Check if user has agency role
     */
    public function isAgency(): bool
    {
        return $this->hasRole(self::ROLE_AGENCY);
    }

    /**
     * Check if user has driver role
     */
    public function isDriver(): bool
    {
        return $this->hasRole(self::ROLE_DRIVER);
    }

    /**
     * Check if user has regular user role
     */
    public function isUser(): bool
    {
        return $this->hasRole(self::ROLE_USER);
    }

    /**
     * Get user's primary role
     */
    public function getPrimaryRole(): string
    {
        if ($this->isAdmin()) return self::ROLE_ADMIN;
        if ($this->isAgency()) return self::ROLE_AGENCY;
        if ($this->isDriver()) return self::ROLE_DRIVER;
        return self::ROLE_USER;
    }

    /**
     * Check if driver is available for new trips
     */
    public function isDriverAvailable(): bool
    {
        return $this->isDriver() && 
               $this->driver_status === self::DRIVER_STATUS_AVAILABLE &&
               $this->is_active;
    }

    /**
     * Get user's full name
     */
    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    /**
     * Get user's profile photo URL
     */
    public function getProfilePhotoUrlAttribute(): ?string
    {
        if ($this->profile_photo) {
            return asset('storage/profile-photos/' . $this->profile_photo);
        }
        
        // Return default avatar
        return asset('images/default-avatar.png');
    }

    /**
     * Scope for active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for verified users
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope for available drivers
     */
    public function scopeAvailableDrivers($query)
    {
        return $query->role(self::ROLE_DRIVER)
                    ->where('driver_status', self::DRIVER_STATUS_AVAILABLE)
                    ->where('is_active', true);
    }

    /**
     * Scope for users by role
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->role($role);
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Set driver status
     */
    public function setDriverStatus(string $status): bool
    {
        if (!$this->isDriver()) {
            return false;
        }

        $validStatuses = [
            self::DRIVER_STATUS_AVAILABLE,
            self::DRIVER_STATUS_BUSY,
            self::DRIVER_STATUS_OFFLINE,
            self::DRIVER_STATUS_ON_TRIP
        ];

        if (!in_array($status, $validStatuses)) {
            return false;
        }

        return $this->update(['driver_status' => $status]);
    }

    /**
     * Get user's active booking
     */
    public function getActiveBooking()
    {
        return $this->bookings()
                   ->whereIn('status', ['confirmed', 'in_progress'])
                   ->latest()
                   ->first();
    }

    /**
     * Get user's current trip (for drivers)
     */
    public function getCurrentTrip()
    {
        return $this->driverBookings()
                   ->where('status', 'in_progress')
                   ->latest()
                   ->first();
    }

    /**
     * Calculate user's rating (average from reviews)
     */
    public function getAverageRating(): float
    {
        return $this->reviews()->avg('rating') ?? 0.0;
    }

    /**
     * Get user's total bookings count
     */
    public function getTotalBookingsCount(): int
    {
        return $this->bookings()->count();
    }

    /**
     * Check if user can make a booking
     */
    public function canMakeBooking(): bool
    {
        return $this->is_verified && 
               $this->is_active && 
               ($this->isUser() || $this->isAgency());
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            // Set default values
            if (empty($user->driver_status) && $user->hasRole(self::ROLE_DRIVER)) {
                $user->driver_status = self::DRIVER_STATUS_OFFLINE;
            }
        });
    }
}