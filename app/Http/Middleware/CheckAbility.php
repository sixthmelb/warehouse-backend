<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk check API token abilities
 * 
 * Usage:
 * Route::middleware(['auth:sanctum', 'ability:items:create'])->group(function () {
 *     // Routes yang butuh ability items:create
 * });
 * 
 * File: app/Http/Middleware/CheckAbility.php
 */
class CheckAbility
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if (!$token || !$token->can($ability)) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient token permissions. Required ability: {$ability}"
            ], 403);
        }

        return $next($request);
    }
}