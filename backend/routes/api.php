<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\VehicleController;
use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\ReviewController;
use App\Http\Controllers\API\AgencyController;
use App\Http\Controllers\API\DriverController;
use App\Http\Controllers\API\GpsController;
use App\Http\Controllers\API\SupportController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\HealthController;

/*
|--------------------------------------------------------------------------
| API Routes for Car Rental Platform
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Health Check Routes (No Authentication Required)
Route::prefix('v1')->group(function () {
    Route::get('/ping', [HealthController::class, 'ping']);
    Route::get('/health', [HealthController::class, 'health']);
    Route::get('/status', [HealthController::class, 'status']);
});

// Public Routes (No Authentication Required)
Route::prefix('v1')->group(function () {
    // Authentication Routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::get('/roles', [AuthController::class, 'getRoles']);
    
    // Public Vehicle Routes
    Route::get('/vehicles/search', [VehicleController::class, 'search']);
    Route::get('/vehicles/featured', [VehicleController::class, 'featured']);
    Route::get('/vehicles/{id}', [VehicleController::class, 'show']);
    Route::post('/vehicles/{id}/check-availability', [VehicleController::class, 'checkAvailability']);
    Route::get('/vehicles/{id}/reviews', [VehicleController::class, 'reviews']);
    
    // Public Agency Routes
    Route::get('/agencies', [AgencyController::class, 'index']);
    Route::get('/agencies/{id}', [AgencyController::class, 'show']);
    Route::get('/agencies/{id}/vehicles', [AgencyController::class, 'vehicles']);
});

// Protected Routes (Authentication Required)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    
    // Authentication Management
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/update-driver-status', [AuthController::class, 'updateDriverStatus']);
    
    // Booking Management
    Route::apiResource('bookings', BookingController::class);
    Route::post('/bookings/{id}/confirm', [BookingController::class, 'confirm']);
    Route::post('/bookings/{id}/start', [BookingController::class, 'start']);
    Route::post('/bookings/{id}/complete', [BookingController::class, 'complete']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
    Route::post('/bookings/{id}/assign-driver', [BookingController::class, 'assignDriver']);
    Route::get('/bookings/{id}/status', [BookingController::class, 'status']);
    
    // Payment Management
    Route::prefix('payments')->group(function () {
        Route::get('/methods', [PaymentController::class, 'methods']);
        Route::post('/process', [PaymentController::class, 'process']);
        Route::get('/{id}', [PaymentController::class, 'show']);
        Route::post('/{id}/refund', [PaymentController::class, 'refund']);
        Route::get('/booking/{bookingId}', [PaymentController::class, 'bookingPayments']);
        Route::get('/user/history', [PaymentController::class, 'userHistory']);
    });
    
    // Review Management
    Route::apiResource('reviews', ReviewController::class)->except(['index']);
    Route::get('/reviews/user', [ReviewController::class, 'userReviews']);
    Route::get('/reviews/vehicle/{vehicleId}', [ReviewController::class, 'vehicleReviews']);
    
    // Support System
    Route::prefix('support')->group(function () {
        Route::apiResource('tickets', SupportController::class);
        Route::post('/tickets/{id}/reply', [SupportController::class, 'reply']);
        Route::post('/tickets/{id}/close', [SupportController::class, 'close']);
        Route::post('/tickets/{id}/reopen', [SupportController::class, 'reopen']);
    });
    
    // User Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'userStats']);
        Route::get('/recent-bookings', [DashboardController::class, 'recentBookings']);
        Route::get('/upcoming-trips', [DashboardController::class, 'upcomingTrips']);
        Route::get('/notifications', [DashboardController::class, 'notifications']);
    });
});

// Agency-Specific Routes
Route::prefix('v1')->middleware(['auth:sanctum', 'role:agency'])->group(function () {
    Route::prefix('agency')->group(function () {
        // Vehicle Management
        Route::apiResource('vehicles', AgencyVehicleController::class);
        Route::post('/vehicles/{id}/upload-images', [AgencyVehicleController::class, 'uploadImages']);
        Route::delete('/vehicles/{id}/images/{imageId}', [AgencyVehicleController::class, 'deleteImage']);
        Route::post('/vehicles/{id}/maintenance', [AgencyVehicleController::class, 'recordMaintenance']);
        
        // Driver Management
        Route::apiResource('drivers', AgencyDriverController::class);
        Route::post('/drivers/{id}/activate', [AgencyDriverController::class, 'activate']);
        Route::post('/drivers/{id}/deactivate', [AgencyDriverController::class, 'deactivate']);
        
        // Booking Management
        Route::get('/bookings', [AgencyBookingController::class, 'index']);
        Route::get('/bookings/{id}', [AgencyBookingController::class, 'show']);
        Route::post('/bookings/{id}/assign-driver', [AgencyBookingController::class, 'assignDriver']);
        
        // Financial Reports
        Route::get('/reports/revenue', [AgencyReportController::class, 'revenue']);
        Route::get('/reports/bookings', [AgencyReportController::class, 'bookings']);
        Route::get('/reports/vehicles', [AgencyReportController::class, 'vehicles']);
        
        // Agency Dashboard
        Route::get('/dashboard/stats', [AgencyDashboardController::class, 'stats']);
        Route::get('/dashboard/recent-activity', [AgencyDashboardController::class, 'recentActivity']);
    });
});

// Driver-Specific Routes
Route::prefix('v1')->middleware(['auth:sanctum', 'role:driver'])->group(function () {
    Route::prefix('driver')->group(function () {
        // Trip Management
        Route::get('/trips/current', [DriverController::class, 'currentTrip']);
        Route::get('/trips/upcoming', [DriverController::class, 'upcomingTrips']);
        Route::get('/trips/history', [DriverController::class, 'tripHistory']);
        Route::post('/trips/{id}/accept', [DriverController::class, 'acceptTrip']);
        Route::post('/trips/{id}/start', [DriverController::class, 'startTrip']);
        Route::post('/trips/{id}/complete', [DriverController::class, 'completeTrip']);
        
        // Status Management
        Route::post('/status/available', [DriverController::class, 'setAvailable']);
        Route::post('/status/busy', [DriverController::class, 'setBusy']);
        Route::post('/status/offline', [DriverController::class, 'setOffline']);
        
        // Emergency
        Route::post('/emergency/report', [DriverController::class, 'reportEmergency']);
        
        // Dashboard
        Route::get('/dashboard/stats', [DriverController::class, 'stats']);
    });
});

// GPS Tracking Routes
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::prefix('gps')->group(function () {
        Route::post('/start-tracking', [GpsController::class, 'startTracking']);
        Route::post('/update-location', [GpsController::class, 'updateLocation']);
        Route::post('/end-tracking', [GpsController::class, 'endTracking']);
        Route::get('/tracking/{bookingId}', [GpsController::class, 'getTracking']);
        Route::get('/live-location/{bookingId}', [GpsController::class, 'getLiveLocation']);
    });
});

// Mobile App Specific Routes
Route::prefix('v1/mobile')->middleware('auth:sanctum')->group(function () {
    Route::post('/login', [MobileController::class, 'login']);
    Route::get('/dashboard', [MobileController::class, 'dashboard']);
    Route::get('/config', [MobileController::class, 'config']);
    Route::post('/device-token', [MobileController::class, 'updateDeviceToken']);
    Route::post('/location-permission', [MobileController::class, 'updateLocationPermission']);
});

// Admin Routes
Route::prefix('v1')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::prefix('admin')->group(function () {
        // User Management
        Route::apiResource('users', AdminUserController::class);
        Route::post('/users/{id}/activate', [AdminUserController::class, 'activate']);
        Route::post('/users/{id}/deactivate', [AdminUserController::class, 'deactivate']);
        Route::post('/users/{id}/verify', [AdminUserController::class, 'verify']);
        Route::post('/users/{id}/assign-role', [AdminUserController::class, 'assignRole']);
        
        // Agency Management
        Route::apiResource('agencies', AdminAgencyController::class);
        Route::post('/agencies/{id}/approve', [AdminAgencyController::class, 'approve']);
        Route::post('/agencies/{id}/suspend', [AdminAgencyController::class, 'suspend']);
        
        // Vehicle Management
        Route::get('/vehicles', [AdminVehicleController::class, 'index']);
        Route::get('/vehicles/{id}', [AdminVehicleController::class, 'show']);
        Route::post('/vehicles/{id}/approve', [AdminVehicleController::class, 'approve']);
        Route::post('/vehicles/{id}/suspend', [AdminVehicleController::class, 'suspend']);
        
        // Booking Management
        Route::get('/bookings', [AdminBookingController::class, 'index']);
        Route::get('/bookings/{id}', [AdminBookingController::class, 'show']);
        Route::post('/bookings/{id}/resolve-dispute', [AdminBookingController::class, 'resolveDispute']);
        
        // Payment Management
        Route::get('/payments', [AdminPaymentController::class, 'index']);
        Route::get('/payments/{id}', [AdminPaymentController::class, 'show']);
        Route::post('/payments/{id}/investigate', [AdminPaymentController::class, 'investigate']);
        
        // Support Management
        Route::get('/support/tickets', [AdminSupportController::class, 'index']);
        Route::get('/support/tickets/{id}', [AdminSupportController::class, 'show']);
        Route::post('/support/tickets/{id}/assign', [AdminSupportController::class, 'assign']);
        Route::post('/support/tickets/{id}/escalate', [AdminSupportController::class, 'escalate']);
        
        // System Settings
        Route::get('/settings', [AdminSettingsController::class, 'index']);
        Route::put('/settings', [AdminSettingsController::class, 'update']);
        Route::post('/settings/api-keys', [AdminSettingsController::class, 'updateApiKeys']);
        Route::post('/settings/payment-gateways', [AdminSettingsController::class, 'updatePaymentGateways']);
        
        // Analytics & Reports
        Route::get('/analytics/overview', [AdminAnalyticsController::class, 'overview']);
        Route::get('/analytics/revenue', [AdminAnalyticsController::class, 'revenue']);
        Route::get('/analytics/bookings', [AdminAnalyticsController::class, 'bookings']);
        Route::get('/analytics/users', [AdminAnalyticsController::class, 'users']);
        Route::get('/analytics/vehicles', [AdminAnalyticsController::class, 'vehicles']);
        
        // System Monitoring
        Route::get('/system/health', [AdminSystemController::class, 'health']);
        Route::get('/system/logs', [AdminSystemController::class, 'logs']);
        Route::get('/system/performance', [AdminSystemController::class, 'performance']);
        Route::post('/system/cache/clear', [AdminSystemController::class, 'clearCache']);
        Route::post('/system/maintenance/enable', [AdminSystemController::class, 'enableMaintenance']);
        Route::post('/system/maintenance/disable', [AdminSystemController::class, 'disableMaintenance']);
    });
});

// Webhook Routes (No Authentication, but should have webhook signature verification)
Route::prefix('webhooks')->group(function () {
    Route::post('/stripe', [WebhookController::class, 'stripe']);
    Route::post('/paypal', [WebhookController::class, 'paypal']);
    Route::post('/firebase', [WebhookController::class, 'firebase']);
});

// Fallback Route for API
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found'
    ], 404);
});

// Rate Limiting (Applied to all API routes)
Route::middleware('throttle:api')->group(function () {
    // All above routes are automatically wrapped in rate limiting
});

/*
|--------------------------------------------------------------------------
| API Route Documentation
|--------------------------------------------------------------------------
|
| Authentication Endpoints:
| POST   /api/v1/register              - User registration
| POST   /api/v1/login                 - User login
| POST   /api/v1/logout                - User logout
| GET    /api/v1/profile               - Get user profile
| PUT    /api/v1/profile               - Update user profile
|
| Vehicle Endpoints:
| GET    /api/v1/vehicles/search       - Search vehicles with filters
| GET    /api/v1/vehicles/{id}         - Get vehicle details
| POST   /api/v1/vehicles/{id}/check   - Check vehicle availability
|
| Booking Endpoints:
| GET    /api/v1/bookings              - List user bookings
| POST   /api/v1/bookings              - Create new booking
| GET    /api/v1/bookings/{id}         - Get booking details
| PUT    /api/v1/bookings/{id}         - Update booking
|
| Payment Endpoints:
| GET    /api/v1/payments/methods      - Get available payment methods
| POST   /api/v1/payments/process      - Process payment
| GET    /api/v1/payments/{id}         - Get payment details
|
| Health Check Endpoints:
| GET    /api/v1/ping                  - Simple health check
| GET    /api/v1/health                - Detailed health status
| GET    /api/v1/status                - Application statistics
|
*/