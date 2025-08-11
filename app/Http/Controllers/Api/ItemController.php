<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * ItemController untuk item management system
 * Handle CRUD operations untuk item/barang
 * 
 * Endpoints:
 * - GET /api/v1/items (index)
 * - POST /api/v1/items (store)
 * - GET /api/v1/items/{id} (show)
 * - PUT /api/v1/items/{id} (update)
 * - DELETE /api/v1/items/{id} (destroy)
 * 
 * File: app/Http/Controllers/Api/ItemController.php
 */
class ItemController extends Controller
{
    /**
     * Get list items dengan filtering dan pagination
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Item::with(['category']);

            // Filter berdasarkan status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter berdasarkan category
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Filter berdasarkan brand
            if ($request->has('brand')) {
                $query->where('brand', 'like', '%' . $request->brand . '%');
            }

            // Filter berdasarkan storage type
            if ($request->has('storage_type')) {
                $query->where('storage_type', $request->storage_type);
            }

            // Filter items dengan stock rendah
            if ($request->boolean('low_stock')) {
                $query->lowStock();
            }

            // Filter items yang habis
            if ($request->boolean('out_of_stock')) {
                $query->outOfStock();
            }

            // Filter items dengan batch tracking
            if ($request->boolean('batch_tracking')) {
                $query->withBatchTracking();
            }

            // Search berdasarkan nama, SKU, atau barcode
            if ($request->has('search')) {
                $query->search($request->search);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $items = $query->paginate($perPage);

            // Transform data
            $items->getCollection()->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'barcode' => $item->barcode,
                    'name' => $item->name,
                    'description' => $item->description,
                    'category' => [
                        'id' => $item->category->id,
                        'name' => $item->category->name,
                        'full_name' => $item->category->full_name,
                    ],
                    'brand' => $item->brand,
                    'model' => $item->model,
                    'dimensions' => $item->dimensions,
                    'weight' => $item->weight,
                    'unit' => $item->unit,
                    'pricing' => [
                        'purchase_price' => $item->purchase_price,
                        'selling_price' => $item->selling_price,
                        'profit_margin' => $item->profit_margin,
                        'profit_percentage' => $item->profit_percentage,
                        'currency' => $item->currency,
                    ],
                    'stock_info' => [
                        'minimum_stock' => $item->minimum_stock,
                        'maximum_stock' => $item->maximum_stock,
                        'reorder_point' => $item->reorder_point,
                        'total_stock' => $item->getTotalStock(),
                    ],
                    'tracking' => [
                        'has_expiry' => $item->has_expiry,
                        'batch_tracking' => $item->batch_tracking,
                        'serial_tracking' => $item->serial_tracking,
                    ],
                    'storage_type' => $item->storage_type,
                    'storage_type_label' => $item->storage_type_label,
                    'status' => $item->status,
                    'status_label' => $item->status_label,
                    'image_url' => $item->image_url,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Items retrieved successfully',
                'data' => $items->items(),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                    'last_page' => $items->lastPage(),
                    'from' => $items->firstItem(),
                    'to' => $items->lastItem(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create item baru
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'sku' => 'nullable|string|max:50|unique:items',
                'barcode' => 'nullable|string|max:50|unique:items',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category_id' => 'required|exists:categories,id',
                'brand' => 'nullable|string|max:100',
                'model' => 'nullable|string|max:100',
                'weight' => 'nullable|numeric|min:0',
                'length' => 'nullable|numeric|min:0',
                'width' => 'nullable|numeric|min:0',
                'height' => 'nullable|numeric|min:0',
                'color' => 'nullable|string|max:50',
                'size' => 'nullable|string|max:50',
                'unit' => 'required|string|max:20',
                'items_per_package' => 'nullable|integer|min:1',
                'package_type' => 'nullable|string|max:50',
                'purchase_price' => 'nullable|numeric|min:0',
                'selling_price' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|size:3',
                'minimum_stock' => 'nullable|integer|min:0',
                'maximum_stock' => 'nullable|integer|min:0',
                'reorder_point' => 'nullable|integer|min:0',
                'reorder_quantity' => 'nullable|integer|min:0',
                'storage_type' => 'nullable|in:normal,cold,frozen,hazardous',
                'storage_notes' => 'nullable|string',
                'has_expiry' => 'nullable|boolean',
                'batch_tracking' => 'nullable|boolean',
                'serial_tracking' => 'nullable|boolean',
                'status' => 'nullable|in:active,inactive,discontinued',
                'custom_fields' => 'nullable|array',
                'notes' => 'nullable|string',
                'image_url' => 'nullable|url',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create item
            $item = Item::create($request->all());

            // Load relationship
            $item->load('category');

            return response()->json([
                'success' => true,
                'message' => 'Item created successfully',
                'data' => [
                    'item' => [
                        'id' => $item->id,
                        'sku' => $item->sku,
                        'name' => $item->name,
                        'category' => $item->category->name,
                        'brand' => $item->brand,
                        'unit' => $item->unit,
                        'purchase_price' => $item->purchase_price,
                        'selling_price' => $item->selling_price,
                        'status' => $item->status_label,
                        'created_at' => $item->created_at,
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detail item
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $item = Item::with(['category'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Item retrieved successfully',
                'data' => [
                    'item' => [
                        'id' => $item->id,
                        'sku' => $item->sku,
                        'barcode' => $item->barcode,
                        'name' => $item->name,
                        'description' => $item->description,
                        'category' => [
                            'id' => $item->category->id,
                            'name' => $item->category->name,
                            'full_name' => $item->category->full_name,
                            'breadcrumb' => $item->category->breadcrumb,
                        ],
                        'physical_info' => [
                            'brand' => $item->brand,
                            'model' => $item->model,
                            'weight' => $item->weight,
                            'dimensions' => $item->dimensions,
                            'volume' => $item->volume,
                            'color' => $item->color,
                            'size' => $item->size,
                        ],
                        'packaging' => [
                            'unit' => $item->unit,
                            'items_per_package' => $item->items_per_package,
                            'package_type' => $item->package_type,
                        ],
                        'pricing' => [
                            'purchase_price' => $item->purchase_price,
                            'selling_price' => $item->selling_price,
                            'profit_margin' => $item->profit_margin,
                            'profit_percentage' => $item->profit_percentage,
                            'currency' => $item->currency,
                        ],
                        'inventory' => [
                            'minimum_stock' => $item->minimum_stock,
                            'maximum_stock' => $item->maximum_stock,
                            'reorder_point' => $item->reorder_point,
                            'reorder_quantity' => $item->reorder_quantity,
                            'stock_summary' => $item->getStockSummary(),
                        ],
                        'storage' => [
                            'storage_type' => $item->storage_type,
                            'storage_type_label' => $item->storage_type_label,
                            'storage_notes' => $item->storage_notes,
                        ],
                        'tracking' => [
                            'has_expiry' => $item->has_expiry,
                            'batch_tracking' => $item->batch_tracking,
                            'serial_tracking' => $item->serial_tracking,
                        ],
                        'status' => $item->status,
                        'status_label' => $item->status_label,
                        'custom_fields' => $item->custom_fields,
                        'notes' => $item->notes,
                        'image_url' => $item->image_url,
                        'qr_code' => $item->generateQRCode(),
                        'average_cost' => $item->calculateAverageCost(),
                        'recent_movements' => $item->getRecentMovements(5),
                        'recent_transactions' => $item->getTransactionHistory(5),
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update item
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $item = Item::findOrFail($id);

            // Validasi input
            $validator = Validator::make($request->all(), [
                'sku' => 'sometimes|required|string|max:50|unique:items,sku,' . $item->id,
                'barcode' => 'nullable|string|max:50|unique:items,barcode,' . $item->id,
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'category_id' => 'sometimes|required|exists:categories,id',
                'brand' => 'nullable|string|max:100',
                'model' => 'nullable|string|max:100',
                'weight' => 'nullable|numeric|min:0',
                'length' => 'nullable|numeric|min:0',
                'width' => 'nullable|numeric|min:0',
                'height' => 'nullable|numeric|min:0',
                'color' => 'nullable|string|max:50',
                'size' => 'nullable|string|max:50',
                'unit' => 'sometimes|required|string|max:20',
                'items_per_package' => 'nullable|integer|min:1',
                'package_type' => 'nullable|string|max:50',
                'purchase_price' => 'nullable|numeric|min:0',
                'selling_price' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|size:3',
                'minimum_stock' => 'nullable|integer|min:0',
                'maximum_stock' => 'nullable|integer|min:0',
                'reorder_point' => 'nullable|integer|min:0',
                'reorder_quantity' => 'nullable|integer|min:0',
                'storage_type' => 'nullable|in:normal,cold,frozen,hazardous',
                'storage_notes' => 'nullable|string',
                'has_expiry' => 'nullable|boolean',
                'batch_tracking' => 'nullable|boolean',
                'serial_tracking' => 'nullable|boolean',
                'status' => 'nullable|in:active,inactive,discontinued',
                'custom_fields' => 'nullable|array',
                'notes' => 'nullable|string',
                'image_url' => 'nullable|url',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update item
            $item->update($request->all());
            $item->load('category');

            return response()->json([
                'success' => true,
                'message' => 'Item updated successfully',
                'data' => [
                    'item' => [
                        'id' => $item->id,
                        'sku' => $item->sku,
                        'name' => $item->name,
                        'category' => $item->category->name,
                        'brand' => $item->brand,
                        'unit' => $item->unit,
                        'purchase_price' => $item->purchase_price,
                        'selling_price' => $item->selling_price,
                        'status' => $item->status_label,
                        'updated_at' => $item->updated_at,
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete item
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $item = Item::findOrFail($id);

            // Check apakah item masih punya stock
            if ($item->getTotalStock() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete item with existing stock'
                ], 400);
            }

            // Check apakah item masih ada di transaksi aktif
            $activeTransactions = $item->transactionDetails()->whereHas('transaction', function ($query) {
                $query->whereIn('status', ['draft', 'pending', 'approved']);
            })->count();

            if ($activeTransactions > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete item with active transactions'
                ], 400);
            }

            // Soft delete item
            $item->delete();

            return response()->json([
                'success' => true,
                'message' => 'Item deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get item stock info di semua warehouse
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function stock($id): JsonResponse
    {
        try {
            $item = Item::with(['itemStocks.warehouse'])->findOrFail($id);

            $stockData = $item->itemStocks->map(function ($stock) {
                return [
                    'warehouse' => [
                        'id' => $stock->warehouse->id,
                        'name' => $stock->warehouse->name,
                        'code' => $stock->warehouse->code,
                    ],
                    'current_stock' => $stock->current_stock,
                    'available_stock' => $stock->available_stock,
                    'reserved_stock' => $stock->reserved_stock,
                    'stock_status' => $stock->stock_status,
                    'stock_status_label' => $stock->stock_status_label,
                    'reorder_alert' => $stock->reorder_alert,
                    'expiry_alert' => $stock->expiry_alert,
                    'last_movement_date' => $stock->last_movement_date,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Item stock information retrieved successfully',
                'data' => [
                    'item' => [
                        'id' => $item->id,
                        'sku' => $item->sku,
                        'name' => $item->name,
                        'unit' => $item->unit,
                    ],
                    'total_stock' => $item->getTotalStock(),
                    'stock_by_warehouse' => $stockData,
                    'stock_summary' => $item->getStockSummary(),
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve item stock information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get items untuk dropdown/lookup
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function lookup(Request $request): JsonResponse
    {
        try {
            $query = Item::active();

            // Filter by category if specified
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Filter by brand if specified
            if ($request->has('brand')) {
                $query->where('brand', $request->brand);
            }

            // Search if specified
            if ($request->has('search')) {
                $query->search($request->search);
            }

            $items = $query->orderBy('name')
                          ->limit($request->get('limit', 50))
                          ->get(['id', 'sku', 'name', 'brand', 'unit', 'purchase_price', 'selling_price']);

            $data = $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'label' => "{$item->sku} - {$item->name}",
                    'brand' => $item->brand,
                    'unit' => $item->unit,
                    'purchase_price' => $item->purchase_price,
                    'selling_price' => $item->selling_price,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Item lookup data retrieved successfully',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve item lookup data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get items yang perlu reorder
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function reorderAlerts(Request $request): JsonResponse
    {
        try {
            $query = Item::with(['itemStocks.warehouse'])
                        ->whereHas('itemStocks', function ($q) {
                            $q->where('reorder_alert', true);
                        });

            // Filter by warehouse if specified
            if ($request->has('warehouse_id')) {
                $query->whereHas('itemStocks', function ($q) use ($request) {
                    $q->where('warehouse_id', $request->warehouse_id);
                });
            }

            $items = $query->get();

            $data = $items->map(function ($item) use ($request) {
                $stocks = $item->itemStocks;
                
                // Filter by warehouse if specified
                if ($request->has('warehouse_id')) {
                    $stocks = $stocks->where('warehouse_id', $request->warehouse_id);
                }

                return [
                    'item' => [
                        'id' => $item->id,
                        'sku' => $item->sku,
                        'name' => $item->name,
                        'brand' => $item->brand,
                        'unit' => $item->unit,
                    ],
                    'stocks' => $stocks->map(function ($stock) {
                        return [
                            'warehouse' => [
                                'id' => $stock->warehouse->id,
                                'name' => $stock->warehouse->name,
                            ],
                            'current_stock' => $stock->current_stock,
                            'reorder_point' => $stock->reorder_point,
                            'recommended_quantity' => $stock->getRecommendedReorderQuantity(),
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Reorder alerts retrieved successfully',
                'data' => $data,
                'summary' => [
                    'total_items_need_reorder' => $data->count(),
                    'total_alerts' => $data->sum(function ($item) {
                        return $item['stocks']->count();
                    }),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reorder alerts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update bulk item prices
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkUpdatePrices(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'items' => 'required|array|min:1',
                'items.*.id' => 'required|exists:items,id',
                'items.*.purchase_price' => 'nullable|numeric|min:0',
                'items.*.selling_price' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updatedItems = [];
            $errors = [];

            foreach ($request->items as $itemData) {
                try {
                    $item = Item::findOrFail($itemData['id']);
                    
                    $updateData = [];
                    if (isset($itemData['purchase_price'])) {
                        $updateData['purchase_price'] = $itemData['purchase_price'];
                    }
                    if (isset($itemData['selling_price'])) {
                        $updateData['selling_price'] = $itemData['selling_price'];
                    }

                    if (!empty($updateData)) {
                        $item->update($updateData);
                        $updatedItems[] = [
                            'id' => $item->id,
                            'sku' => $item->sku,
                            'name' => $item->name,
                            'purchase_price' => $item->purchase_price,
                            'selling_price' => $item->selling_price,
                        ];
                    }

                } catch (\Exception $e) {
                    $errors[] = [
                        'item_id' => $itemData['id'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Bulk price update completed',
                'data' => [
                    'updated_items' => $updatedItems,
                    'errors' => $errors,
                    'summary' => [
                        'total_processed' => count($request->items),
                        'successful_updates' => count($updatedItems),
                        'failed_updates' => count($errors),
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk update prices',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}