<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * AuthController untuk warehouse management system
 * Handle authentication dengan Laravel Sanctum
 * 
 * Endpoints:
 * - POST /api/v1/login
 * - POST /api/v1/logout  
 * - POST /api/v1/register
 * - GET /api/v1/user
 * - POST /api/v1/refresh
 * 
 * File: app/Http/Controllers/Api/AuthController.php
 */
class AuthController extends Controller
{
    /**
     * Login user dan generate API token
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        try {
            // Validasi input login
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string|min:6',
                'device_name' => 'nullable|string|max:255', // Untuk nama device token
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Cari user berdasarkan email
            $user = User::where('email', $request->email)->first();

            // Validasi user exists dan password benar
            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Check apakah user aktif
            if ($user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is not active'
                ], 403);
            }

            // Revoke existing tokens (single session) - optional
            if ($request->boolean('single_session', false)) {
                $user->tokens()->delete();
            }

            // Generate token dengan abilities sesuai role
            $deviceName = $request->device_name ?? 'API Token';
            $token = $user->createApiToken($deviceName);

            // Log successful login
            \Log::info('User login successful', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'employee_id' => $user->employee_id,
                        'role' => $user->role,
                        'role_label' => $user->role_label,
                        'phone' => $user->phone,
                        'status' => $user->status,
                        'warehouse_access' => $user->warehouse_access,
                        'accessible_warehouses' => $user->getAccessibleWarehouses(),
                    ],
                    'token' => $token->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_in' => config('sanctum.expiration') * 60, // Convert to seconds
                    'abilities' => $user->getTokenAbilities(),
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Login error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Register user baru (hanya untuk admin)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        try {
            // Check apakah user yang request adalah admin
            $currentUser = Auth::user();
            if (!$currentUser || !$currentUser->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin can register new users'
                ], 403);
            }

            // Validasi input register
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'employee_id' => 'nullable|string|max:255|unique:users',
                'phone' => 'nullable|string|max:20',
                'role' => 'required|in:admin,manager,staff',
                'warehouse_access' => 'nullable|array',
                'warehouse_access.*' => 'exists:warehouses,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create user baru
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'employee_id' => $request->employee_id,
                'phone' => $request->phone,
                'role' => $request->role,
                'status' => 'active',
                'warehouse_access' => $request->warehouse_access,
            ]);

            // Log user creation
            \Log::info('New user registered', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'created_by' => $currentUser->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'employee_id' => $user->employee_id,
                        'role' => $user->role,
                        'role_label' => $user->role_label,
                        'phone' => $user->phone,
                        'status' => $user->status,
                        'warehouse_access' => $user->warehouse_access,
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Registration error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout user dan revoke token
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            // Log successful logout
            \Log::info('User logout successful', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Logout error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current authenticated user
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function user(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'employee_id' => $user->employee_id,
                        'role' => $user->role,
                        'role_label' => $user->role_label,
                        'phone' => $user->phone,
                        'status' => $user->status,
                        'warehouse_access' => $user->warehouse_access,
                        'accessible_warehouses' => $user->getAccessibleWarehouses(),
                        'abilities' => $user->getTokenAbilities(),
                        'current_token_name' => $request->user()->currentAccessToken()->name,
                        'last_login' => $user->updated_at,
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Get user error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh token - generate new token dengan revoke yang lama
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $currentToken = $request->user()->currentAccessToken();
            
            // Simpan nama token lama
            $tokenName = $currentToken->name;
            
            // Revoke current token
            $currentToken->delete();
            
            // Generate token baru dengan nama yang sama
            $newToken = $user->createApiToken($tokenName);

            // Log token refresh
            \Log::info('Token refreshed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'token_name' => $tokenName,
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $newToken->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_in' => config('sanctum.expiration') * 60,
                    'abilities' => $user->getTokenAbilities(),
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Token refresh error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revoke all user tokens (logout from all devices)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function revokeAllTokens(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Revoke all tokens
            $user->tokens()->delete();

            // Log token revocation
            \Log::info('All tokens revoked', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'All tokens revoked successfully'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Revoke all tokens error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke tokens',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's active tokens
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getTokens(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tokens = $user->tokens()->get();

            $tokenList = $tokens->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'created_at' => $token->created_at,
                    'last_used_at' => $token->last_used_at,
                    'is_current' => $token->id === request()->user()->currentAccessToken()->id,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'tokens' => $tokenList,
                    'total' => $tokens->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Get tokens error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get tokens',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change password
     * 
     * @param Request $request
     * @return JsonResponse
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
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();

            // Check current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            // Update password
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            // Log password change
            \Log::info('Password changed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Change password error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user profile
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update profile
            $user->update($request->only(['name', 'phone', 'email']));

            // Log profile update
            \Log::info('Profile updated', [
                'user_id' => $user->id,
                'email' => $user->email,
                'updated_fields' => array_keys($request->only(['name', 'phone', 'email'])),
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'employee_id' => $user->employee_id,
                        'phone' => $user->phone,
                        'role' => $user->role,
                        'status' => $user->status,
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Update profile error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}