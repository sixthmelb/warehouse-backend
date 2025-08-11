<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk check role access dalam warehouse management system
 * 
 * Usage:
 * Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
 *     // Admin only routes
 * });
 * 
 * Route::middleware(['auth:sanctum', 'role:admin,manager'])->group(function () {
 *     // Admin atau Manager routes
 * });
 * 
 * File: app/Http/Middleware/CheckRole.php
 */
class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        // Check apakah user sudah login
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Check apakah user aktif
        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Account is not active'
            ], 403);
        }

        // Check role user apakah sesuai dengan yang diizinkan
        if (!empty($roles) && !in_array($user->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions. Required roles: ' . implode(', ', $roles)
            ], 403);
        }

        return $next($request);
    }
}