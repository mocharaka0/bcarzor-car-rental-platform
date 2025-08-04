<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Vehicle Controller for Car Rental Platform API
 * 
 * Handles vehicle search, details, availability, and management
 */
class VehicleController extends Controller
{
    /**
     * Search vehicles with filters
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'location_lat' => 'nullable|numeric|between:-90,90',
                'location_lng' => 'nullable|numeric|between:-180,180',
                'radius' => 'nullable|numeric|min:1|max:100',
                'start_date' => 'nullable|date|after_or_equal:today',
                'end_date' => 'nullable|date|after:start_date',
                'vehicle_type' => 'nullable|string|in:economy,compact,midsize,fullsize,luxury,suv,truck,van,convertible',
                'fuel_type' => 'nullable|string|in:gasoline,diesel,hybrid,electric,plugin_hybrid',
                'transmission' => 'nullable|string|in:manual,automatic,cvt',
                'min_price' => 'nullable|numeric|min:0',
                'max_price' => 'nullable|numeric|min:0',
                'seats' => 'nullable|integer|min:2|max:12',
                'features' => 'nullable|array',
                'features.*' => 'string',
                'sort_by' => 'nullable|string|in:price,rating,distance,newest',
                'sort_order' => 'nullable|string|in:asc,desc',
                'per_page' => 'nullable|integer|min:1|max:50',
                'agency_id' => 'nullable|exists:agencies,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Start building the query
            $query = QueryBuilder::for(Vehicle::class)
                ->allowedFilters([
                    'vehicle_type',
                    'fuel_type',
                    'transmission',
                    'agency_id'
                ])
                ->allowedSorts(['daily_rate', 'average_rating', 'created_at'])
                ->with(['agency', 'media']);

            // Filter available vehicles
            $query->available();

            // Location-based filtering
            if ($request->has(['location_lat', 'location_lng'])) {
                $radius = $request->radius ?? 10; // Default 10km radius
                $query->withinRadius(
                    $request->location_lat,
                    $request->location_lng,
                    $radius
                );
            }

            // Date availability filtering
            if ($request->has(['start_date', 'end_date'])) {
                $startDate = $request->start_date;
                $endDate = $request->end_date;
                
                $query->whereDoesntHave('bookings', function ($bookingQuery) use ($startDate, $endDate) {
                    $bookingQuery->whereIn('status', ['confirmed', 'in_progress'])
                                 ->where(function ($q) use ($startDate, $endDate) {
                                     $q->whereBetween('start_date', [$startDate, $endDate])
                                       ->orWhereBetween('end_date', [$startDate, $endDate])
                                       ->orWhere(function ($subQ) use ($startDate, $endDate) {
                                           $subQ->where('start_date', '<=', $startDate)
                                                ->where('end_date', '>=', $endDate);
                                       });
                                 });
                });
            }

            // Price range filtering
            if ($request->has(['min_price', 'max_price'])) {
                $query->inPriceRange($request->min_price, $request->max_price);
            } elseif ($request->has('max_price')) {
                $query->where('daily_rate', '<=', $request->max_price);
            } elseif ($request->has('min_price')) {
                $query->where('daily_rate', '>=', $request->min_price);
            }

            // Seats filtering
            if ($request->has('seats')) {
                $query->where('seats', '>=', $request->seats);
            }

            // Features filtering
            if ($request->has('features') && !empty($request->features)) {
                $query->withFeatures($request->features);
            }

            // Sorting
            $sortBy = $request->sort_by ?? 'price';
            $sortOrder = $request->sort_order ?? 'asc';

            switch ($sortBy) {
                case 'price':
                    $query->orderBy('daily_rate', $sortOrder);
                    break;
                case 'rating':
                    $query->orderBy('average_rating', $sortOrder === 'asc' ? 'desc' : 'asc');
                    break;
                case 'distance':
                    if ($request->has(['location_lat', 'location_lng'])) {
                        // Distance sorting would require raw SQL or custom ordering
                        $query->orderBy('created_at', 'desc');
                    } else {
                        $query->orderBy('created_at', 'desc');
                    }
                    break;
                case 'newest':
                    $query->orderBy('created_at', 'desc');
                    break;
                default:
                    $query->orderBy('daily_rate', 'asc');
            }

            // Pagination
            $perPage = $request->per_page ?? 20;
            $vehicles = $query->paginate($perPage);

            // Transform the data
            $vehiclesData = $vehicles->getCollection()->map(function ($vehicle) use ($request) {
                $distance = null;
                if ($request->has(['location_lat', 'location_lng'])) {
                    $distance = $vehicle->getDistanceFrom(
                        $request->location_lat,
                        $request->location_lng
                    );
                }

                return [
                    'id' => $vehicle->id,
                    'agency' => [
                        'id' => $vehicle->agency->id,
                        'name' => $vehicle->agency->name,
                        'rating' => $vehicle->agency->average_rating ?? 0,
                    ],
                    'make' => $vehicle->make,
                    'model' => $vehicle->model,
                    'year' => $vehicle->year,
                    'color' => $vehicle->color,
                    'vehicle_type' => $vehicle->vehicle_type,
                    'fuel_type' => $vehicle->fuel_type,
                    'transmission' => $vehicle->transmission,
                    'seats' => $vehicle->seats,
                    'doors' => $vehicle->doors,
                    'features' => $vehicle->features,
                    'daily_rate' => $vehicle->daily_rate,
                    'hourly_rate' => $vehicle->hourly_rate,
                    'weekly_rate' => $vehicle->weekly_rate,
                    'security_deposit' => $vehicle->security_deposit,
                    'rating' => $vehicle->average_rating ?? 0,
                    'total_bookings' => $vehicle->total_bookings ?? 0,
                    'primary_image' => $vehicle->primary_image,
                    'thumbnail_image' => $vehicle->thumbnail_image,
                    'location' => [
                        'address' => $vehicle->location_address,
                        'latitude' => $vehicle->location_latitude,
                        'longitude' => $vehicle->location_longitude,
                        'distance_km' => $distance ? round($distance, 2) : null,
                    ],
                    'availability' => $vehicle->isAvailableForBooking(),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Vehicles retrieved successfully',
                'data' => [
                    'vehicles' => $vehiclesData,
                    'pagination' => [
                        'current_page' => $vehicles->currentPage(),
                        'last_page' => $vehicles->lastPage(),
                        'per_page' => $vehicles->perPage(),
                        'total' => $vehicles->total(),
                        'from' => $vehicles->firstItem(),
                        'to' => $vehicles->lastItem(),
                    ],
                    'filters' => [
                        'applied' => array_filter($request->only([
                            'vehicle_type', 'fuel_type', 'transmission',
                            'min_price', 'max_price', 'seats', 'features'
                        ])),
                        'available' => [
                            'vehicle_types' => Vehicle::select('vehicle_type')
                                                    ->distinct()
                                                    ->pluck('vehicle_type'),
                            'fuel_types' => Vehicle::select('fuel_type')
                                                  ->distinct()
                                                  ->pluck('fuel_type'),
                            'transmissions' => Vehicle::select('transmission')
                                                     ->distinct()
                                                     ->pluck('transmission'),
                            'price_range' => [
                                'min' => Vehicle::available()->min('daily_rate'),
                                'max' => Vehicle::available()->max('daily_rate'),
                            ],
                        ]
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle search failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get vehicle details
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $vehicle = Vehicle::with([
                'agency',
                'reviews.user',
                'maintenanceRecords' => function ($query) {
                    $query->latest()->limit(5);
                }
            ])->find($id);

            if (!$vehicle) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle not found'
                ], 404);
            }

            // Calculate distance if location provided
            $distance = null;
            if ($request->has(['location_lat', 'location_lng'])) {
                $distance = $vehicle->getDistanceFrom(
                    $request->location_lat,
                    $request->location_lng
                );
            }

            // Get AI-suggested pricing if dates provided
            $aiSuggestedPrice = null;
            if ($request->has(['start_date', 'end_date'])) {
                $aiSuggestedPrice = $vehicle->getAiSuggestedPrice(
                    $request->start_date,
                    $request->end_date
                );
            }

            $vehicleData = [
                'id' => $vehicle->id,
                'agency' => [
                    'id' => $vehicle->agency->id,
                    'name' => $vehicle->agency->name,
                    'phone' => $vehicle->agency->phone,
                    'email' => $vehicle->agency->email,
                    'rating' => $vehicle->agency->average_rating ?? 0,
                    'total_vehicles' => $vehicle->agency->vehicles()->count(),
                ],
                'specifications' => [
                    'make' => $vehicle->make,
                    'model' => $vehicle->model,
                    'year' => $vehicle->year,
                    'color' => $vehicle->color,
                    'license_plate' => $vehicle->license_plate,
                    'vehicle_type' => $vehicle->vehicle_type,
                    'fuel_type' => $vehicle->fuel_type,
                    'transmission' => $vehicle->transmission,
                    'seats' => $vehicle->seats,
                    'doors' => $vehicle->doors,
                    'engine_size' => $vehicle->engine_size,
                    'mileage' => $vehicle->mileage,
                ],
                'pricing' => [
                    'daily_rate' => $vehicle->daily_rate,
                    'hourly_rate' => $vehicle->hourly_rate,
                    'weekly_rate' => $vehicle->weekly_rate,
                    'monthly_rate' => $vehicle->monthly_rate,
                    'security_deposit' => $vehicle->security_deposit,
                    'ai_suggested_price' => $aiSuggestedPrice,
                ],
                'features' => $vehicle->features,
                'description' => $vehicle->description,
                'images' => $vehicle->vehicle_images,
                'location' => [
                    'address' => $vehicle->location_address,
                    'latitude' => $vehicle->location_latitude,
                    'longitude' => $vehicle->location_longitude,
                    'distance_km' => $distance ? round($distance, 2) : null,
                ],
                'availability' => [
                    'is_available' => $vehicle->isAvailableForBooking(),
                    'status' => $vehicle->status,
                    'next_available_date' => $this->getNextAvailableDate($vehicle),
                ],
                'rating' => [
                    'average' => $vehicle->average_rating ?? 0,
                    'total_reviews' => $vehicle->reviews->count(),
                    'total_bookings' => $vehicle->total_bookings ?? 0,
                ],
                'reviews' => $vehicle->reviews->take(5)->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'user_name' => $review->user->name,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'created_at' => $review->created_at->toDateString(),
                    ];
                }),
                'maintenance' => [
                    'last_service_date' => $vehicle->last_service_date?->toDateString(),
                    'maintenance_due_date' => $vehicle->maintenance_due_date?->toDateString(),
                    'needs_maintenance' => $vehicle->needsMaintenance(),
                ],
                'insurance' => [
                    'expiry_date' => $vehicle->insurance_expiry?->toDateString(),
                    'is_expired' => $vehicle->isInsuranceExpired(),
                ],
                'created_at' => $vehicle->created_at->toISOString(),
                'updated_at' => $vehicle->updated_at->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Vehicle details retrieved successfully',
                'data' => [
                    'vehicle' => $vehicleData
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get vehicle details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check vehicle availability for specific dates
     */
    public function checkAvailability(Request $request, int $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after:start_date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $vehicle = Vehicle::find($id);

            if (!$vehicle) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle not found'
                ], 404);
            }

            $isAvailable = $vehicle->isAvailableForDates(
                $request->start_date,
                $request->end_date
            );

            $pricing = [];
            if ($isAvailable) {
                $pricing = [
                    'daily_price' => $vehicle->calculatePrice(
                        $request->start_date,
                        $request->end_date,
                        'daily'
                    ),
                    'total_days' => \Carbon\Carbon::parse($request->start_date)
                                                 ->diffInDays($request->end_date) ?: 1,
                    'ai_suggested_price' => $vehicle->getAiSuggestedPrice(
                        $request->start_date,
                        $request->end_date
                    ),
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Availability checked successfully',
                'data' => [
                    'vehicle_id' => $vehicle->id,
                    'is_available' => $isAvailable,
                    'period' => [
                        'start_date' => $request->start_date,
                        'end_date' => $request->end_date,
                    ],
                    'pricing' => $pricing,
                    'conflicts' => $isAvailable ? [] : $this->getBookingConflicts($vehicle, $request->start_date, $request->end_date),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Availability check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get vehicle reviews
     */
    public function reviews(Request $request, int $id): JsonResponse
    {
        try {
            $vehicle = Vehicle::find($id);

            if (!$vehicle) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle not found'
                ], 404);
            }

            $perPage = $request->per_page ?? 10;
            $reviews = $vehicle->reviews()
                              ->with('user')
                              ->latest()
                              ->paginate($perPage);

            $reviewsData = $reviews->getCollection()->map(function ($review) {
                return [
                    'id' => $review->id,
                    'user' => [
                        'name' => $review->user->name,
                        'profile_photo_url' => $review->user->profile_photo_url,
                    ],
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at->diffForHumans(),
                    'created_date' => $review->created_at->toDateString(),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Vehicle reviews retrieved successfully',
                'data' => [
                    'reviews' => $reviewsData,
                    'pagination' => [
                        'current_page' => $reviews->currentPage(),
                        'last_page' => $reviews->lastPage(),
                        'per_page' => $reviews->perPage(),
                        'total' => $reviews->total(),
                    ],
                    'summary' => [
                        'average_rating' => $vehicle->average_rating ?? 0,
                        'total_reviews' => $reviews->total(),
                        'rating_breakdown' => $this->getRatingBreakdown($vehicle),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get vehicle reviews',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get featured vehicles
     */
    public function featured(Request $request): JsonResponse
    {
        try {
            $limit = $request->limit ?? 8;

            $vehicles = Vehicle::available()
                              ->with(['agency', 'media'])
                              ->where('average_rating', '>=', 4.0)
                              ->orderBy('average_rating', 'desc')
                              ->orderBy('total_bookings', 'desc')
                              ->limit($limit)
                              ->get();

            $vehiclesData = $vehicles->map(function ($vehicle) {
                return [
                    'id' => $vehicle->id,
                    'agency_name' => $vehicle->agency->name,
                    'make' => $vehicle->make,
                    'model' => $vehicle->model,
                    'year' => $vehicle->year,
                    'vehicle_type' => $vehicle->vehicle_type,
                    'daily_rate' => $vehicle->daily_rate,
                    'rating' => $vehicle->average_rating ?? 0,
                    'total_bookings' => $vehicle->total_bookings ?? 0,
                    'thumbnail_image' => $vehicle->thumbnail_image,
                    'location_address' => $vehicle->location_address,
                    'features' => array_slice($vehicle->features ?? [], 0, 3), // First 3 features
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Featured vehicles retrieved successfully',
                'data' => [
                    'vehicles' => $vehiclesData
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get featured vehicles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get next available date for vehicle
     */
    private function getNextAvailableDate(Vehicle $vehicle): ?string
    {
        $nextBooking = $vehicle->bookings()
                              ->whereIn('status', ['confirmed', 'in_progress'])
                              ->where('end_date', '>', now())
                              ->orderBy('end_date')
                              ->first();

        return $nextBooking ? $nextBooking->end_date->addDay()->toDateString() : null;
    }

    /**
     * Get booking conflicts for dates
     */
    private function getBookingConflicts(Vehicle $vehicle, string $startDate, string $endDate): array
    {
        return $vehicle->bookings()
                      ->whereIn('status', ['confirmed', 'in_progress'])
                      ->where(function ($query) use ($startDate, $endDate) {
                          $query->whereBetween('start_date', [$startDate, $endDate])
                                ->orWhereBetween('end_date', [$startDate, $endDate])
                                ->orWhere(function ($q) use ($startDate, $endDate) {
                                    $q->where('start_date', '<=', $startDate)
                                      ->where('end_date', '>=', $endDate);
                                });
                      })
                      ->get(['start_date', 'end_date', 'status'])
                      ->toArray();
    }

    /**
     * Get rating breakdown
     */
    private function getRatingBreakdown(Vehicle $vehicle): array
    {
        $breakdown = [];
        for ($i = 1; $i <= 5; $i++) {
            $count = $vehicle->reviews()->where('rating', $i)->count();
            $breakdown[$i] = $count;
        }
        return $breakdown;
    }
}