<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemStock;
use App\Models\StockMovement;
use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

/**
 * StockController untuk stock management system
 * Handle stock monitoring, alerts, dan cycle counting
 * 
 * Endpoints:
 * - GET /api/v1/stocks (index)
 * - GET /api/v1/stocks/alerts (alerts)
 * - GET /api/v1/stocks/movements (movements)
 * - POST /api/v1/stocks/cycle-count (cycle count)
 * - GET /api/v1/stocks/valuation (valuation)
 * 
 * File: app/Http/Controllers/Api/StockController.php
 */
class StockController extends Controller
{
    /**
     * Get stock levels dengan filtering
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ItemStock::with(['item.category', 'warehouse']);

            // Filter berdasarkan warehouse access untuk non-admin
            $user = $request->user();
            if (!$user->isAdmin()) {
                $accessibleWarehouses = $user->warehouse_access ?? [];
                if (!empty($accessibleWarehouses)) {
                    $query->whereIn('warehouse_id', $accessibleWarehouses);
                }
            }

            // Filter berdasarkan warehouse
            if ($request->has('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            // Filter berdasarkan item
            if ($request->has('item_id')) {
                $query->where('item_id', $request->item_id);
            }

            // Filter berdasarkan category
            if ($request->has('category_id')) {
                $query->whereHas('item', function ($q) use ($request) {
                    $q->where('category_id', $request->category_id);
                });
            }

            // Filter berdasarkan stock status
            if ($request->has('stock_status')) {
                $query->where('stock_status', $request->stock_status);
            }

            // Filter stock yang ada (current_stock > 0)
            if ($request->boolean('has_stock')) {
                $query->hasStock();
            }

            // Filter items dengan reorder alert
            if ($request->boolean('reorder_alert')) {
                $query->needsReorder();
            }

            // Filter items dengan expiry alert
            if ($request->boolean('expiry_alert')) {
                $query->expiryAlert();
            }

            // Search berdasarkan item name atau SKU
            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('item', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'updated_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $stocks = $query->paginate($perPage);

            // Transform data
            $stocks->getCollection()->transform(function ($stock) {
                return [
                    'id' => $stock->id,
                    'item' => [
                        'id' => $stock->item->id,
                        'sku' => $stock->item->sku,
                        'name' => $stock->item->name,
                        'brand' => $stock->item->brand,
                        'category' => $stock->item->category->name,
                        'unit' => $stock->item->unit,
                        'image_url' => $stock->item->image_url,
                    ],
                    'warehouse' => [
                        'id' => $stock->warehouse->id,
                        'name' => $stock->warehouse->name,
                        'code' => $stock->warehouse->code,
                    ],
                    'stock_levels' => [
                        'current_stock' => $stock->current_stock,
                        'available_stock' => $stock->available_stock,
                        'reserved_stock' => $stock->reserved_stock,
                        'minimum_stock' => $stock->minimum_stock,
                        'maximum_stock' => $stock->maximum_stock,
                        'reorder_point' => $stock->reorder_point,
                    ],
                    'stock_status' => $stock->stock_status,
                    'stock_status_label' => $stock->stock_status_label,
                    'stock_utilization' => $stock->stock_utilization,
                    'valuation' => [
                        'average_cost' => $stock->average_cost,
                        'last_cost' => $stock->last_cost,
                        'total_value' => $stock->total_value,
                        'currency' => $stock->currency,
                    ],
                    'alerts' => [
                        'reorder_alert' => $stock->reorder_alert,
                        'expiry_alert' => $stock->expiry_alert,
                        'recommended_reorder_qty' => $stock->getRecommendedReorderQuantity(),
                    ],
                    'location' => [
                        'primary_location' => $stock->primary_location,
                        'storage_zone' => $stock->storage_zone,
                    ],
                    'last_activity' => [
                        'last_movement_date' => $stock->last_movement_date,
                        'last_movement_type' => $stock->last_movement_type,
                        'last_movement_quantity' => $stock->last_movement_quantity,
                        'days_since_last_movement' => $stock->days_since_last_movement,
                    ],
                    'cycle_count' => [
                        'last_count_date' => $stock->last_count_date,
                        'last_count_quantity' => $stock->last_count_quantity,
                        'count_variance' => $stock->count_variance,
                        'next_count_due' => $stock->next_count_due,
                        'days_until_next_count' => $stock->days_until_next_count,
                    ],
                    'updated_at' => $stock->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Stock levels retrieved successfully',
                'data' => $stocks->items(),
                'meta' => [
                    'current_page' => $stocks->currentPage(),
                    'per_page' => $stocks->perPage(),
                    'total' => $stocks->total(),
                    'last_page' => $stocks->lastPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stock levels',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock alerts (low stock, out of stock, expiry, reorder)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function alerts(Request $request): JsonResponse
    {
        try {
            $warehouseId = $request->get('warehouse_id');
            
            // Get summary alerts
            $alerts = ItemStock::getStockAlerts($warehouseId);

            // Get detailed alerts
            $lowStockItems = ItemStock::with(['item', 'warehouse'])
                                    ->lowStock()
                                    ->when($warehouseId, function ($q) use ($warehouseId) {
                                        return $q->where('warehouse_id', $warehouseId);
                                    })
                                    ->limit(10)
                                    ->get();

            $outOfStockItems = ItemStock::with(['item', 'warehouse'])
                                       ->outOfStock()
                                       ->when($warehouseId, function ($q) use ($warehouseId) {
                                           return $q->where('warehouse_id', $warehouseId);
                                       })
                                       ->limit(10)
                                       ->get();

            $reorderItems = ItemStock::with(['item', 'warehouse'])
                                    ->needsReorder()
                                    ->when($warehouseId, function ($q) use ($warehouseId) {
                                        return $q->where('warehouse_id', $warehouseId);
                                    })
                                    ->limit(10)
                                    ->get();

            $expiryItems = ItemStock::with(['item', 'warehouse'])
                                   ->expiryAlert()
                                   ->when($warehouseId, function ($q) use ($warehouseId) {
                                       return $q->where('warehouse_id', $warehouseId);
                                   })
                                   ->limit(10)
                                   ->get();

            return response()->json([
                'success' => true,
                'message' => 'Stock alerts retrieved successfully',
                'data' => [
                    'summary' => $alerts,
                    'low_stock_items' => $lowStockItems->map(function ($stock) {
                        return [
                            'item_name' => $stock->item->name,
                            'item_sku' => $stock->item->sku,
                            'warehouse_name' => $stock->warehouse->name,
                            'current_stock' => $stock->current_stock,
                            'minimum_stock' => $stock->minimum_stock,
                            'shortage' => $stock->minimum_stock - $stock->current_stock,
                        ];
                    }),
                    'out_of_stock_items' => $outOfStockItems->map(function ($stock) {
                        return [
                            'item_name' => $stock->item->name,
                            'item_sku' => $stock->item->sku,
                            'warehouse_name' => $stock->warehouse->name,
                            'last_movement_date' => $stock->last_movement_date,
                            'days_out_of_stock' => $stock->days_since_last_movement,
                        ];
                    }),
                    'reorder_items' => $reorderItems->map(function ($stock) {
                        return [
                            'item_name' => $stock->item->name,
                            'item_sku' => $stock->item->sku,
                            'warehouse_name' => $stock->warehouse->name,
                            'current_stock' => $stock->current_stock,
                            'reorder_point' => $stock->reorder_point,
                            'recommended_quantity' => $stock->getRecommendedReorderQuantity(),
                        ];
                    }),
                    'expiry_items' => $expiryItems->map(function ($stock) {
                        return [
                            'item_name' => $stock->item->name,
                            'item_sku' => $stock->item->sku,
                            'warehouse_name' => $stock->warehouse->name,
                            'expiring_stock' => $stock->expiring_stock,
                            'earliest_expiry' => $stock->earliest_expiry,
                        ];
                    }),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stock alerts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock movements history
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function movements(Request $request): JsonResponse
    {
        try {
            $query = StockMovement::with(['item', 'warehouse', 'user', 'transaction']);

            // Filter berdasarkan warehouse access untuk non-admin
            $user = $request->user();
            if (!$user->isAdmin()) {
                $accessibleWarehouses = $user->warehouse_access ?? [];
                if (!empty($accessibleWarehouses)) {
                    $query->whereIn('warehouse_id', $accessibleWarehouses);
                }
            }

            // Filter berdasarkan warehouse
            if ($request->has('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            // Filter berdasarkan item
            if ($request->has('item_id')) {
                $query->where('item_id', $request->item_id);
            }

            // Filter berdasarkan movement type
            if ($request->has('movement_type')) {
                $query->where('movement_type', $request->movement_type);
            }

            // Filter berdasarkan date range
            if ($request->has('date_from')) {
                $query->where('movement_date', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->where('movement_date', '<=', $request->date_to);
            }

            // Filter movements yang approved
            if ($request->boolean('approved_only', true)) {
                $query->approved();
            }

            // Exclude corrections kecuali diminta
            if (!$request->boolean('include_corrections')) {
                $query->notCorrection();
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'movement_date');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $movements = $query->paginate($perPage);

            // Transform data
            $movements->getCollection()->transform(function ($movement) {
                return [
                    'id' => $movement->id,
                    'movement_date' => $movement->movement_date,
                    'movement_type' => $movement->movement_type,
                    'movement_type_label' => $movement->movement_type_label,
                    'movement_reason' => $movement->movement_reason,
                    'movement_reason_label' => $movement->movement_reason_label,
                    'item' => [
                        'id' => $movement->item->id,
                        'sku' => $movement->item->sku,
                        'name' => $movement->item->name,
                    ],
                    'warehouse' => [
                        'id' => $movement->warehouse->id,
                        'name' => $movement->warehouse->name,
                        'code' => $movement->warehouse->code,
                    ],
                    'quantities' => [
                        'quantity_before' => $movement->quantity_before,
                        'quantity_moved' => $movement->quantity_moved,
                        'quantity_after' => $movement->quantity_after,
                        'unit' => $movement->unit,
                    ],
                    'batch_info' => [
                        'batch_number' => $movement->batch_number,
                        'serial_number' => $movement->serial_number,
                        'expiry_date' => $movement->expiry_date,
                    ],
                    'cost_info' => [
                        'unit_cost' => $movement->unit_cost,
                        'total_cost' => $movement->total_cost,
                        'currency' => $movement->currency,
                    ],
                    'transaction' => $movement->transaction ? [
                        'id' => $movement->transaction->id,
                        'transaction_number' => $movement->transaction->transaction_number,
                        'type' => $movement->transaction->type,
                    ] : null,
                    'user' => [
                        'id' => $movement->user->id,
                        'name' => $movement->user->name,
                    ],
                    'is_approved' => $movement->is_approved,
                    'is_correction' => $movement->is_correction,
                    'is_automated' => $movement->is_automated,
                    'notes' => $movement->notes,
                    'created_at' => $movement->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Stock movements retrieved successfully',
                'data' => $movements->items(),
                'meta' => [
                    'current_page' => $movements->currentPage(),
                    'per_page' => $movements->perPage(),
                    'total' => $movements->total(),
                    'last_page' => $movements->lastPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stock movements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Perform cycle count
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function cycleCount(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'item_id' => 'required|exists:items,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'counted_quantity' => 'required|numeric|min:0',
                'notes' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check warehouse access
            $user = Auth::user();
            if (!$user->isAdmin() && !$user->canAccessWarehouse($request->warehouse_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this warehouse'
                ], 403);
            }

            // Find stock record
            $itemStock = ItemStock::where('item_id', $request->item_id)
                                 ->where('warehouse_id', $request->warehouse_id)
                                 ->first();

            if (!$itemStock) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stock record not found for this item in the specified warehouse'
                ], 404);
            }

            // Perform cycle count
            $result = $itemStock->performCycleCount(
                $request->counted_quantity,
                $user->id,
                $request->notes
            );

            $itemStock->load(['item', 'warehouse']);

            return response()->json([
                'success' => true,
                'message' => 'Cycle count completed successfully',
                'data' => [
                    'cycle_count_result' => $result,
                    'item' => [
                        'id' => $itemStock->item->id,
                        'sku' => $itemStock->item->sku,
                        'name' => $itemStock->item->name,
                    ],
                    'warehouse' => [
                        'id' => $itemStock->warehouse->id,
                        'name' => $itemStock->warehouse->name,
                    ],
                    'updated_stock' => [
                        'current_stock' => $itemStock->current_stock,
                        'stock_status' => $itemStock->stock_status_label,
                        'last_count_date' => $itemStock->last_count_date,
                        'next_count_due' => $itemStock->next_count_due,
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform cycle count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock valuation summary
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function valuation(Request $request): JsonResponse
    {
        try {
            $warehouseId = $request->get('warehouse_id');
            
            // Get valuation summary
            $valuation = ItemStock::getStockValuation($warehouseId);

            // Get top valuable items
            $query = ItemStock::with(['item', 'warehouse'])
                             ->where('current_stock', '>', 0)
                             ->when($warehouseId, function ($q) use ($warehouseId) {
                                 return $q->where('warehouse_id', $warehouseId);
                             });

            $topValueItems = $query->orderBy('total_value', 'desc')
                                  ->limit(10)
                                  ->get()
                                  ->map(function ($stock) {
                                      return [
                                          'item_name' => $stock->item->name,
                                          'item_sku' => $stock->item->sku,
                                          'warehouse_name' => $stock->warehouse->name,
                                          'current_stock' => $stock->current_stock,
                                          'average_cost' => $stock->average_cost,
                                          'total_value' => $stock->total_value,
                                          'currency' => $stock->currency,
                                      ];
                                  });

            // Get valuation by category
            $categoryValuation = ItemStock::with(['item.category'])
                                         ->where('current_stock', '>', 0)
                                         ->when($warehouseId, function ($q) use ($warehouseId) {
                                             return $q->where('warehouse_id', $warehouseId);
                                         })
                                         ->get()
                                         ->groupBy('item.category.name')
                                         ->map(function ($stocks, $categoryName) {
                                             return [
                                                 'category_name' => $categoryName,
                                                 'total_items' => $stocks->count(),
                                                 'total_quantity' => $stocks->sum('current_stock'),
                                                 'total_value' => $stocks->sum('total_value'),
                                             ];
                                         })
                                         ->sortByDesc('total_value')
                                         ->values();

            return response()->json([
                'success' => true,
                'message' => 'Stock valuation retrieved successfully',
                'data' => [
                    'summary' => $valuation,
                    'top_value_items' => $topValueItems,
                    'valuation_by_category' => $categoryValuation,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stock valuation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock report berdasarkan filters
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function report(Request $request): JsonResponse
    {
        try {
            $warehouseId = $request->get('warehouse_id');
            $status = $request->get('status');

            $report = ItemStock::generateStockReport($warehouseId, $status);

            return response()->json([
                'success' => true,
                'message' => 'Stock report generated successfully',
                'data' => $report,
                'summary' => [
                    'total_items' => $report->count(),
                    'total_stock_value' => $report->sum('total_value'),
                    'by_status' => $report->groupBy('stock_status')->map->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate stock report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reserve stock untuk order
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function reserve(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'item_id' => 'required|exists:items,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'quantity' => 'required|numeric|min:0.01',
                'notes' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find stock record
            $itemStock = ItemStock::where('item_id', $request->item_id)
                                 ->where('warehouse_id', $request->warehouse_id)
                                 ->first();

            if (!$itemStock) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stock record not found'
                ], 404);
            }

            // Reserve stock
            $itemStock->reserveStock($request->quantity);

            return response()->json([
                'success' => true,
                'message' => 'Stock reserved successfully',
                'data' => [
                    'item_id' => $request->item_id,
                    'warehouse_id' => $request->warehouse_id,
                    'reserved_quantity' => $request->quantity,
                    'updated_stock' => [
                        'current_stock' => $itemStock->current_stock,
                        'available_stock' => $itemStock->available_stock,
                        'reserved_stock' => $itemStock->reserved_stock,
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reserve stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Release reserved stock
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function release(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'item_id' => 'required|exists:items,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'quantity' => 'required|numeric|min:0.01',
                'notes' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find stock record
            $itemStock = ItemStock::where('item_id', $request->item_id)
                                 ->where('warehouse_id', $request->warehouse_id)
                                 ->first();

            if (!$itemStock) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stock record not found'
                ], 404);
            }

            // Release reserved stock
            $itemStock->releaseReservedStock($request->quantity);

            return response()->json([
                'success' => true,
                'message' => 'Reserved stock released successfully',
                'data' => [
                    'item_id' => $request->item_id,
                    'warehouse_id' => $request->warehouse_id,
                    'released_quantity' => $request->quantity,
                    'updated_stock' => [
                        'current_stock' => $itemStock->current_stock,
                        'available_stock' => $itemStock->available_stock,
                        'reserved_stock' => $itemStock->reserved_stock,
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to release reserved stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}