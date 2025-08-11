<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * WarehouseController untuk warehouse management system
 * Handle CRUD operations untuk warehouse
 * 
 * Endpoints:
 * - GET /api/v1/warehouses (index)
 * - POST /api/v1/warehouses (store) 
 * - GET /api/v1/warehouses/{id} (show)
 * - PUT /api/v1/warehouses/{id} (update)
 * - DELETE /api/v1/warehouses/{id} (destroy)
 * 
 * File: app/Http/Controllers/Api/WarehouseController.php
 */
class WarehouseController extends Controller
{
    /**
     * Get list warehouses dengan filtering dan pagination
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Warehouse::query();

            // Filter berdasarkan status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter berdasarkan city
            if ($request->has('city')) {
                $query->where('city', 'like', '%' . $request->city . '%');
            }

            // Filter berdasarkan province
            if ($request->has('province')) {
                $query->where('province', 'like', '%' . $request->province . '%');
            }

            // Search berdasarkan nama atau code
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            }

            // Filter warehouse access untuk non-admin
            $user = $request->user();
            if (!$user->isAdmin()) {
                $accessibleWarehouses = $user->warehouse_access ?? [];
                if (!empty($accessibleWarehouses)) {
                    $query->whereIn('id', $accessibleWarehouses);
                }
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $warehouses = $query->paginate($perPage);

            // Transform data dengan additional info
            $warehouses->getCollection()->transform(function ($warehouse) {
                return [
                    'id' => $warehouse->id,
                    'code' => $warehouse->code,
                    'name' => $warehouse->name,
                    'description' => $warehouse->description,
                    'address' => $warehouse->address,
                    'city' => $warehouse->city,
                    'province' => $warehouse->province,
                    'postal_code' => $warehouse->postal_code,
                    'coordinates' => $warehouse->getCoordinates(),
                    'capacity' => $warehouse->capacity,
                    'capacity_unit' => $warehouse->capacity_unit,
                    'status' => $warehouse->status,
                    'status_label' => $warehouse->status_label,
                    'manager' => [
                        'name' => $warehouse->manager_name,
                        'phone' => $warehouse->manager_phone,
                        'email' => $warehouse->manager_email,
                    ],
                    'stock_summary' => $warehouse->getStockSummary(),
                    'created_at' => $warehouse->created_at,
                    'updated_at' => $warehouse->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Warehouses retrieved successfully',
                'data' => $warehouses->items(),
                'meta' => [
                    'current_page' => $warehouses->currentPage(),
                    'per_page' => $warehouses->perPage(),
                    'total' => $warehouses->total(),
                    'last_page' => $warehouses->lastPage(),
                    'from' => $warehouses->firstItem(),
                    'to' => $warehouses->lastItem(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve warehouses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create warehouse baru
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:10|unique:warehouses',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'address' => 'required|string',
                'city' => 'required|string|max:100',
                'province' => 'required|string|max:100',
                'postal_code' => 'nullable|string|max:10',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'capacity' => 'nullable|numeric|min:0',
                'capacity_unit' => 'nullable|string|max:20',
                'status' => 'nullable|in:active,inactive,maintenance',
                'manager_name' => 'nullable|string|max:255',
                'manager_phone' => 'nullable|string|max:20',
                'manager_email' => 'nullable|email|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create warehouse
            $warehouse = Warehouse::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Warehouse created successfully',
                'data' => [
                    'warehouse' => [
                        'id' => $warehouse->id,
                        'code' => $warehouse->code,
                        'name' => $warehouse->name,
                        'description' => $warehouse->description,
                        'address' => $warehouse->full_address,
                        'coordinates' => $warehouse->getCoordinates(),
                        'capacity' => $warehouse->capacity,
                        'capacity_unit' => $warehouse->capacity_unit,
                        'status' => $warehouse->status,
                        'manager' => [
                            'name' => $warehouse->manager_name,
                            'phone' => $warehouse->manager_phone,
                            'email' => $warehouse->manager_email,
                        ],
                        'created_at' => $warehouse->created_at,
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create warehouse',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detail warehouse
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $warehouse = Warehouse::findOrFail($id);

            // Check warehouse access untuk non-admin
            $user = request()->user();
            if (!$user->isAdmin() && !$user->canAccessWarehouse($warehouse->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this warehouse'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Warehouse retrieved successfully',
                'data' => [
                    'warehouse' => [
                        'id' => $warehouse->id,
                        'code' => $warehouse->code,
                        'name' => $warehouse->name,
                        'description' => $warehouse->description,
                        'address' => $warehouse->address,
                        'city' => $warehouse->city,
                        'province' => $warehouse->province,
                        'postal_code' => $warehouse->postal_code,
                        'full_address' => $warehouse->full_address,
                        'coordinates' => $warehouse->getCoordinates(),
                        'capacity' => $warehouse->capacity,
                        'capacity_unit' => $warehouse->capacity_unit,
                        'capacity_utilization' => $warehouse->capacity_utilization,
                        'status' => $warehouse->status,
                        'status_label' => $warehouse->status_label,
                        'manager' => [
                            'name' => $warehouse->manager_name,
                            'phone' => $warehouse->manager_phone,
                            'email' => $warehouse->manager_email,
                        ],
                        'statistics' => [
                            'total_items' => $warehouse->total_items,
                            'total_stock_value' => $warehouse->total_stock_value,
                            'stock_summary' => $warehouse->getStockSummary(),
                        ],
                        'recent_transactions' => $warehouse->getRecentTransactions(5),
                        'created_at' => $warehouse->created_at,
                        'updated_at' => $warehouse->updated_at,
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Warehouse not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve warehouse',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update warehouse
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $warehouse = Warehouse::findOrFail($id);

            // Validasi input
            $validator = Validator::make($request->all(), [
                'code' => 'sometimes|required|string|max:10|unique:warehouses,code,' . $warehouse->id,
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'address' => 'sometimes|required|string',
                'city' => 'sometimes|required|string|max:100',
                'province' => 'sometimes|required|string|max:100',
                'postal_code' => 'nullable|string|max:10',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'capacity' => 'nullable|numeric|min:0',
                'capacity_unit' => 'nullable|string|max:20',
                'status' => 'nullable|in:active,inactive,maintenance',
                'manager_name' => 'nullable|string|max:255',
                'manager_phone' => 'nullable|string|max:20',
                'manager_email' => 'nullable|email|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update warehouse
            $warehouse->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Warehouse updated successfully',
                'data' => [
                    'warehouse' => [
                        'id' => $warehouse->id,
                        'code' => $warehouse->code,
                        'name' => $warehouse->name,
                        'description' => $warehouse->description,
                        'address' => $warehouse->full_address,
                        'coordinates' => $warehouse->getCoordinates(),
                        'capacity' => $warehouse->capacity,
                        'capacity_unit' => $warehouse->capacity_unit,
                        'status' => $warehouse->status,
                        'manager' => [
                            'name' => $warehouse->manager_name,
                            'phone' => $warehouse->manager_phone,
                            'email' => $warehouse->manager_email,
                        ],
                        'updated_at' => $warehouse->updated_at,
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Warehouse not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update warehouse',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete warehouse
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $warehouse = Warehouse::findOrFail($id);

            // Check apakah warehouse masih punya stock atau transaksi aktif
            if ($warehouse->itemStocks()->where('current_stock', '>', 0)->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete warehouse with existing stock'
                ], 400);
            }

            if ($warehouse->transactions()->whereIn('status', ['draft', 'pending', 'approved'])->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete warehouse with active transactions'
                ], 400);
            }

            // Soft delete warehouse
            $warehouse->delete();

            return response()->json([
                'success' => true,
                'message' => 'Warehouse deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Warehouse not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete warehouse',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get warehouses yang accessible oleh user
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function accessible(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $warehouses = $user->getAccessibleWarehouses();

            $data = $warehouses->map(function ($warehouse) {
                return [
                    'id' => $warehouse->id,
                    'code' => $warehouse->code,
                    'name' => $warehouse->name,
                    'city' => $warehouse->city,
                    'status' => $warehouse->status,
                    'status_label' => $warehouse->status_label,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Accessible warehouses retrieved successfully',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve accessible warehouses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get warehouse statistics
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function statistics($id): JsonResponse
    {
        try {
            $warehouse = Warehouse::findOrFail($id);

            // Check warehouse access
            $user = request()->user();
            if (!$user->isAdmin() && !$user->canAccessWarehouse($warehouse->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this warehouse'
                ], 403);
            }

            $stats = [
                'basic_info' => [
                    'total_items' => $warehouse->total_items,
                    'total_stock_value' => $warehouse->total_stock_value,
                    'capacity_utilization' => $warehouse->capacity_utilization,
                ],
                'stock_summary' => $warehouse->getStockSummary(),
                'recent_transactions' => $warehouse->getRecentTransactions(10),
                'alerts' => [
                    'low_stock_items' => $warehouse->itemStocks()->where('stock_status', 'low')->count(),
                    'out_of_stock_items' => $warehouse->itemStocks()->where('stock_status', 'out_of_stock')->count(),
                    'expiring_items' => $warehouse->itemStocks()->where('expiry_alert', true)->count(),
                    'reorder_needed' => $warehouse->itemStocks()->where('reorder_alert', true)->count(),
                ]
            ];

            return response()->json([
                'success' => true,
                'message' => 'Warehouse statistics retrieved successfully',
                'data' => $stats
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Warehouse not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve warehouse statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}