<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Authentication Controller for Car Rental Platform API
 * 
 * Handles user registration, login, logout, and profile management
 */
class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'phone' => 'nullable|string|max:20',
                'date_of_birth' => 'nullable|date|before:today',
                'role' => 'required|string|in:user,agency,driver',
                'license_number' => 'required_if:role,driver|string|max:50',
                'license_expiry' => 'required_if:role,driver|date|after:today',
                'agency_id' => 'required_if:role,driver|exists:agencies,id',
                'address' => 'nullable|string|max:500',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
                'postal_code' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'date_of_birth' => $request->date_of_birth,
                'license_number' => $request->license_number,
                'license_expiry' => $request->license_expiry,
                'agency_id' => $request->agency_id,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'country' => $request->country,
                'postal_code' => $request->postal_code,
                'is_active' => true,
                'is_verified' => false, // Email verification required
            ]);

            // Assign role
            $user->assignRole($request->role);

            // Set driver status if registering as driver
            if ($request->role === 'driver') {
                $user->driver_status = User::DRIVER_STATUS_OFFLINE;
                $user->save();
            }

            // Generate API token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Send email verification (you would implement this)
            // $user->sendEmailVerificationNotification();

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'role' => $user->getPrimaryRole(),
                        'is_verified' => $user->is_verified,
                        'profile_photo_url' => $user->profile_photo_url,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login user
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required',
                'device_name' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $credentials = $request->only('email', 'password');

            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            $user = Auth::user();

            // Check if user is active
            if (!$user->is_active) {
                Auth::logout();
                return response()->json([
                    'success' => false,
                    'message' => 'Account is deactivated. Please contact support.'
                ], 403);
            }

            // Update last login
            $user->updateLastLogin();

            // Generate API token
            $deviceName = $request->device_name ?? 'API Token';
            $token = $user->createToken($deviceName)->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'role' => $user->getPrimaryRole(),
                        'roles' => $user->roles->pluck('name'),
                        'is_verified' => $user->is_verified,
                        'profile_photo_url' => $user->profile_photo_url,
                        'agency_id' => $user->agency_id,
                        'driver_status' => $user->driver_status,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            // Revoke all tokens
            $request->user()->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out from all devices successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get authenticated user profile
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $user->load(['agency', 'roles', 'permissions']);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                        'license_number' => $user->license_number,
                        'license_expiry' => $user->license_expiry?->format('Y-m-d'),
                        'address' => $user->address,
                        'city' => $user->city,
                        'state' => $user->state,
                        'country' => $user->country,
                        'postal_code' => $user->postal_code,
                        'profile_photo_url' => $user->profile_photo_url,
                        'is_verified' => $user->is_verified,
                        'is_active' => $user->is_active,
                        'role' => $user->getPrimaryRole(),
                        'roles' => $user->roles->pluck('name'),
                        'permissions' => $user->getAllPermissions()->pluck('name'),
                        'agency' => $user->agency ? [
                            'id' => $user->agency->id,
                            'name' => $user->agency->name,
                            'status' => $user->agency->status,
                        ] : null,
                        'driver_status' => $user->driver_status,
                        'emergency_contact_name' => $user->emergency_contact_name,
                        'emergency_contact_phone' => $user->emergency_contact_phone,
                        'last_login_at' => $user->last_login_at?->toISOString(),
                        'created_at' => $user->created_at->toISOString(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'phone' => 'sometimes|nullable|string|max:20',
                'date_of_birth' => 'sometimes|nullable|date|before:today',
                'license_number' => 'sometimes|nullable|string|max:50',
                'license_expiry' => 'sometimes|nullable|date|after:today',
                'address' => 'sometimes|nullable|string|max:500',
                'city' => 'sometimes|nullable|string|max:100',
                'state' => 'sometimes|nullable|string|max:100',
                'country' => 'sometimes|nullable|string|max:100',
                'postal_code' => 'sometimes|nullable|string|max:20',
                'emergency_contact_name' => 'sometimes|nullable|string|max:255',
                'emergency_contact_phone' => 'sometimes|nullable|string|max:20',
                'preferences' => 'sometimes|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'profile_photo_url' => $user->profile_photo_url,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => 'required',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            // Check current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 422);
            }

            // Update password
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            // Revoke all tokens to force re-login
            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully. Please log in again.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password change failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request password reset
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::where('email', $request->email)->first();

            if (!$user || !$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found or account is inactive'
                ], 404);
            }

            // Generate password reset token (you would implement this)
            // $user->sendPasswordResetNotification($token);

            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent to your email'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password reset request failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify email address
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'verification_code' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            // Verify the code (you would implement this logic)
            // if (!$this->verifyEmailCode($user, $request->verification_code)) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Invalid verification code'
            //     ], 422);
            // }

            $user->update([
                'is_verified' => true,
                'email_verified_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Email verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update driver status (for drivers only)
     */
    public function updateDriverStatus(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->isDriver()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only drivers can update driver status'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:available,busy,offline,on_trip'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!$user->setDriverStatus($request->status)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update driver status'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Driver status updated successfully',
                'data' => [
                    'driver_status' => $user->driver_status
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Driver status update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available roles for registration
     */
    public function getRoles(): JsonResponse
    {
        try {
            $roles = Role::whereIn('name', ['user', 'agency', 'driver'])
                        ->get(['name', 'guard_name']);

            return response()->json([
                'success' => true,
                'data' => [
                    'roles' => $roles->map(function ($role) {
                        return [
                            'value' => $role->name,
                            'label' => ucfirst($role->name),
                            'description' => $this->getRoleDescription($role->name)
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get roles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get role description
     */
    private function getRoleDescription(string $role): string
    {
        return match($role) {
            'user' => 'Regular customer who can book vehicles',
            'agency' => 'Vehicle rental agency that manages fleet',
            'driver' => 'Driver employed by an agency',
            default => 'Unknown role'
        };
    }
}