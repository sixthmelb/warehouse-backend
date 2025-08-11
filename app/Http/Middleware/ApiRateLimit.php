<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk API rate limiting per user
 * 
 * File: app/Http/Middleware/ApiRateLimit.php
 */
class ApiRateLimit
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, $maxAttempts = 60, $decayMinutes = 1): Response
    {
        $user = $request->user();
        
        if ($user) {
            $key = 'api_rate_limit:' . $user->id;
            $attempts = cache()->get($key, 0);
            
            if ($attempts >= $maxAttempts) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.',
                    'retry_after' => $decayMinutes * 60
                ], 429);
            }
            
            cache()->put($key, $attempts + 1, now()->addMinutes($decayMinutes));
        }

        return $next($request);
    }
}