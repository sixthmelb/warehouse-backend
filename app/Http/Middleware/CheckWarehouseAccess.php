<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk check warehouse access
 * 
 * Usage:
 * Route::middleware(['auth:sanctum', 'warehouse.access'])->group(function () {
 *     // Routes yang butuh warehouse access validation
 * });
 * 
 * File: app/Http/Middleware/CheckWarehouseAccess.php
 */
class CheckWarehouseAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Admin bisa akses semua warehouse
        if ($user && $user->isAdmin()) {
            return $next($request);
        }

        // Get warehouse_id dari request (bisa dari route parameter atau query/body)
        $warehouseId = $request->route('warehouse_id') 
                      ?? $request->input('warehouse_id') 
                      ?? $request->query('warehouse_id');

        if ($warehouseId && !$user->canAccessWarehouse($warehouseId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this warehouse'
            ], 403);
        }

        return $next($request);
    }
}