<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Vehicle Model for Car Rental Platform
 * 
 * Represents rental vehicles with features, availability, and pricing
 */
class Vehicle extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'agency_id',
        'make',
        'model',
        'year',
        'color',
        'license_plate',
        'vin',
        'vehicle_type',
        'fuel_type',
        'transmission',
        'seats',
        'doors',
        'engine_size',
        'mileage',
        'daily_rate',
        'hourly_rate',
        'weekly_rate',
        'monthly_rate',
        'security_deposit',
        'description',
        'features',
        'status',
        'location_address',
        'location_latitude',
        'location_longitude',
        'is_available',
        'maintenance_due_date',
        'insurance_expiry',
        'registration_expiry',
        'last_service_date',
        'total_bookings',
        'average_rating'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'features' => 'array',
        'is_available' => 'boolean',
        'maintenance_due_date' => 'date',
        'insurance_expiry' => 'date',
        'registration_expiry' => 'date',
        'last_service_date' => 'date',
        'daily_rate' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'weekly_rate' => 'decimal:2',
        'monthly_rate' => 'decimal:2',
        'security_deposit' => 'decimal:2',
        'location_latitude' => 'decimal:8',
        'location_longitude' => 'decimal:8',
        'average_rating' => 'decimal:1',
        'total_bookings' => 'integer'
    ];

    /**
     * Vehicle status constants
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_UNAVAILABLE = 'unavailable';
    const STATUS_RETIRED = 'retired';

    /**
     * Vehicle type constants
     */
    const TYPE_ECONOMY = 'economy';
    const TYPE_COMPACT = 'compact';
    const TYPE_MIDSIZE = 'midsize';
    const TYPE_FULLSIZE = 'fullsize';
    const TYPE_LUXURY = 'luxury';
    const TYPE_SUV = 'suv';
    const TYPE_TRUCK = 'truck';
    const TYPE_VAN = 'van';
    const TYPE_CONVERTIBLE = 'convertible';

    /**
     * Fuel type constants
     */
    const FUEL_GASOLINE = 'gasoline';
    const FUEL_DIESEL = 'diesel';
    const FUEL_HYBRID = 'hybrid';
    const FUEL_ELECTRIC = 'electric';
    const FUEL_PLUGIN_HYBRID = 'plugin_hybrid';

    /**
     * Transmission constants
     */
    const TRANSMISSION_MANUAL = 'manual';
    const TRANSMISSION_AUTOMATIC = 'automatic';
    const TRANSMISSION_CVT = 'cvt';

    /**
     * Get the agency that owns this vehicle
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * Get all bookings for this vehicle
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get all reviews for this vehicle
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get vehicle maintenance records
     */
    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(VehicleMaintenance::class);
    }

    /**
     * Get vehicle availability records
     */
    public function availabilityRecords(): HasMany
    {
        return $this->hasMany(VehicleAvailability::class);
    }

    /**
     * Register media collections
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('vehicle_images')
              ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('vehicle_documents')
              ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png']);
    }

    /**
     * Register media conversions
     */
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
              ->width(300)
              ->height(200)
              ->sharpen(10)
              ->performOnCollections('vehicle_images');

        $this->addMediaConversion('large')
              ->width(800)
              ->height(600)
              ->sharpen(10)
              ->performOnCollections('vehicle_images');
    }

    /**
     * Get vehicle's primary image
     */
    public function getPrimaryImageAttribute(): ?string
    {
        $media = $this->getFirstMedia('vehicle_images');
        return $media ? $media->getUrl('large') : null;
    }

    /**
     * Get vehicle's thumbnail image
     */
    public function getThumbnailImageAttribute(): ?string
    {
        $media = $this->getFirstMedia('vehicle_images');
        return $media ? $media->getUrl('thumb') : asset('images/vehicle-placeholder.png');
    }

    /**
     * Get all vehicle images
     */
    public function getVehicleImagesAttribute(): array
    {
        return $this->getMedia('vehicle_images')->map(function ($media) {
            return [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'thumb' => $media->getUrl('thumb'),
                'large' => $media->getUrl('large')
            ];
        })->toArray();
    }

    /**
     * Get vehicle's full name
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->year} {$this->make} {$this->model}";
    }

    /**
     * Get vehicle's display name with type
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->full_name} ({$this->vehicle_type})";
    }

    /**
     * Check if vehicle is available for booking
     */
    public function isAvailableForBooking(): bool
    {
        return $this->is_available && 
               $this->status === self::STATUS_ACTIVE &&
               !$this->hasActiveBooking();
    }

    /**
     * Check if vehicle has an active booking
     */
    public function hasActiveBooking(): bool
    {
        return $this->bookings()
                   ->whereIn('status', ['confirmed', 'in_progress'])
                   ->exists();
    }

    /**
     * Check availability for specific date range
     */
    public function isAvailableForDates(string $startDate, string $endDate): bool
    {
        if (!$this->isAvailableForBooking()) {
            return false;
        }

        // Check for conflicting bookings
        return !$this->bookings()
                    ->whereIn('status', ['confirmed', 'in_progress'])
                    ->where(function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('start_date', [$startDate, $endDate])
                              ->orWhereBetween('end_date', [$startDate, $endDate])
                              ->orWhere(function ($q) use ($startDate, $endDate) {
                                  $q->where('start_date', '<=', $startDate)
                                    ->where('end_date', '>=', $endDate);
                              });
                    })
                    ->exists();
    }

    /**
     * Calculate price for given dates and duration type
     */
    public function calculatePrice(string $startDate, string $endDate, string $durationType = 'daily'): float
    {
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        switch ($durationType) {
            case 'hourly':
                $hours = $start->diffInHours($end);
                return $hours * $this->hourly_rate;

            case 'weekly':
                $weeks = $start->diffInWeeks($end);
                return $weeks * $this->weekly_rate;

            case 'monthly':
                $months = $start->diffInMonths($end);
                return $months * $this->monthly_rate;

            case 'daily':
            default:
                $days = $start->diffInDays($end);
                if ($days === 0) $days = 1; // Minimum 1 day
                return $days * $this->daily_rate;
        }
    }

    /**
     * Get AI-suggested price based on demand and other factors
     */
    public function getAiSuggestedPrice(string $startDate, string $endDate): float
    {
        // Base price calculation
        $basePrice = $this->calculatePrice($startDate, $endDate);

        // AI pricing factors (this would integrate with OpenAI service)
        $demandMultiplier = $this->calculateDemandMultiplier($startDate, $endDate);
        $seasonalMultiplier = $this->calculateSeasonalMultiplier($startDate);
        $vehiclePopularityMultiplier = $this->calculatePopularityMultiplier();

        return $basePrice * $demandMultiplier * $seasonalMultiplier * $vehiclePopularityMultiplier;
    }

    /**
     * Calculate demand multiplier based on bookings
     */
    private function calculateDemandMultiplier(string $startDate, string $endDate): float
    {
        // Simple demand calculation - this could be enhanced with AI
        $nearbyBookings = $this->bookings()
                              ->whereBetween('start_date', [
                                  \Carbon\Carbon::parse($startDate)->subDays(7),
                                  \Carbon\Carbon::parse($endDate)->addDays(7)
                              ])
                              ->count();

        return 1 + ($nearbyBookings * 0.1); // 10% increase per nearby booking
    }

    /**
     * Calculate seasonal multiplier
     */
    private function calculateSeasonalMultiplier(string $date): float
    {
        $month = \Carbon\Carbon::parse($date)->month;
        
        // Summer months (June-August) have higher demand
        if (in_array($month, [6, 7, 8])) {
            return 1.2;
        }
        
        // Holiday months (December, January) have higher demand
        if (in_array($month, [12, 1])) {
            return 1.15;
        }
        
        return 1.0;
    }

    /**
     * Calculate popularity multiplier based on ratings and bookings
     */
    private function calculatePopularityMultiplier(): float
    {
        $rating = $this->average_rating ?? 0;
        $bookingCount = $this->total_bookings ?? 0;

        $ratingMultiplier = 1 + (($rating - 3) * 0.1); // Adjust based on rating above/below 3
        $popularityMultiplier = 1 + min($bookingCount * 0.01, 0.5); // Max 50% increase

        return $ratingMultiplier * $popularityMultiplier;
    }

    /**
     * Update vehicle rating based on reviews
     */
    public function updateAverageRating(): void
    {
        $averageRating = $this->reviews()->avg('rating') ?? 0;
        $this->update(['average_rating' => round($averageRating, 1)]);
    }

    /**
     * Update total bookings count
     */
    public function updateBookingsCount(): void
    {
        $totalBookings = $this->bookings()->where('status', 'completed')->count();
        $this->update(['total_bookings' => $totalBookings]);
    }

    /**
     * Check if vehicle needs maintenance
     */
    public function needsMaintenance(): bool
    {
        return $this->maintenance_due_date && 
               $this->maintenance_due_date->isPast();
    }

    /**
     * Check if insurance is expired
     */
    public function isInsuranceExpired(): bool
    {
        return $this->insurance_expiry && 
               $this->insurance_expiry->isPast();
    }

    /**
     * Check if registration is expired
     */
    public function isRegistrationExpired(): bool
    {
        return $this->registration_expiry && 
               $this->registration_expiry->isPast();
    }

    /**
     * Get distance from a given location
     */
    public function getDistanceFrom(float $latitude, float $longitude): float
    {
        if (!$this->location_latitude || !$this->location_longitude) {
            return 999999; // Very high number if no location set
        }

        // Haversine formula for calculating distance
        $earthRadius = 6371; // km

        $deltaLat = deg2rad($latitude - $this->location_latitude);
        $deltaLng = deg2rad($longitude - $this->location_longitude);

        $a = sin($deltaLat/2) * sin($deltaLat/2) +
             cos(deg2rad($this->location_latitude)) * cos(deg2rad($latitude)) *
             sin($deltaLng/2) * sin($deltaLng/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    /**
     * Scope for available vehicles
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)
                    ->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for vehicles by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('vehicle_type', $type);
    }

    /**
     * Scope for vehicles within radius
     */
    public function scopeWithinRadius($query, float $latitude, float $longitude, float $radiusKm)
    {
        return $query->whereRaw("
            (6371 * acos(cos(radians(?)) * cos(radians(location_latitude)) * 
            cos(radians(location_longitude) - radians(?)) + 
            sin(radians(?)) * sin(radians(location_latitude)))) <= ?
        ", [$latitude, $longitude, $latitude, $radiusKm]);
    }

    /**
     * Scope for vehicles by price range
     */
    public function scopeInPriceRange($query, float $minPrice, float $maxPrice)
    {
        return $query->whereBetween('daily_rate', [$minPrice, $maxPrice]);
    }

    /**
     * Scope for vehicles with specific features
     */
    public function scopeWithFeatures($query, array $features)
    {
        foreach ($features as $feature) {
            $query->whereJsonContains('features', $feature);
        }
        return $query;
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($vehicle) {
            // Set default values
            if (empty($vehicle->status)) {
                $vehicle->status = self::STATUS_ACTIVE;
            }
            if (is_null($vehicle->is_available)) {
                $vehicle->is_available = true;
            }
        });
    }
}