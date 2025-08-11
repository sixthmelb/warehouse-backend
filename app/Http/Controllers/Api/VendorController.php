<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * VendorController untuk vendor management system
 * Handle CRUD operations untuk vendor/supplier
 * 
 * Endpoints:
 * - GET /api/v1/vendors (index)
 * - POST /api/v1/vendors (store)
 * - GET /api/v1/vendors/{id} (show)
 * - PUT /api/v1/vendors/{id} (update)
 * - DELETE /api/v1/vendors/{id} (destroy)
 * 
 * File: app/Http/Controllers/Api/VendorController.php
 */
class VendorController extends Controller
{
    /**
     * Get list vendors dengan filtering dan pagination
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Vendor::query();

            // Filter berdasarkan status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter berdasarkan vendor type
            if ($request->has('vendor_type')) {
                $query->where('vendor_type', $request->vendor_type);
            }

            // Filter berdasarkan city
            if ($request->has('city')) {
                $query->where('city', 'like', '%' . $request->city . '%');
            }

            // Filter berdasarkan rating minimal
            if ($request->has('min_rating')) {
                $query->where('rating', '>=', $request->min_rating);
            }

            // Search berdasarkan nama atau code
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Filter hanya vendor aktif (exclude blacklist)
            if ($request->boolean('active_only', false)) {
                $query->notBlacklisted();
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');
            
            // Special sorting untuk rating (handle null values)
            if ($sortBy === 'rating') {
                $query->orderByRaw('rating IS NULL, rating ' . $sortOrder);
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $vendors = $query->paginate($perPage);

            // Transform data
            $vendors->getCollection()->transform(function ($vendor) {
                return [
                    'id' => $vendor->id,
                    'code' => $vendor->code,
                    'name' => $vendor->name,
                    'description' => $vendor->description,
                    'vendor_type' => $vendor->vendor_type,
                    'vendor_type_label' => $vendor->vendor_type_label,
                    'address' => $vendor->full_address,
                    'city' => $vendor->city,
                    'province' => $vendor->province,
                    'phone' => $vendor->phone,
                    'email' => $vendor->email,
                    'website' => $vendor->website,
                    'contact_person' => $vendor->contact_person_full,
                    'payment_terms' => [
                        'days' => $vendor->payment_terms_days,
                        'method' => $vendor->payment_method,
                        'method_label' => $vendor->payment_method_label,
                    ],
                    'rating' => $vendor->rating,
                    'rating_stars' => $vendor->rating_stars,
                    'status' => $vendor->status,
                    'status_label' => $vendor->status_label,
                    'is_reliable' => $vendor->isReliable(),
                    'total_transactions' => $vendor->getTotalTransactions(),
                    'created_at' => $vendor->created_at,
                    'updated_at' => $vendor->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Vendors retrieved successfully',
                'data' => $vendors->items(),
                'meta' => [
                    'current_page' => $vendors->currentPage(),
                    'per_page' => $vendors->perPage(),
                    'total' => $vendors->total(),
                    'last_page' => $vendors->lastPage(),
                    'from' => $vendors->firstItem(),
                    'to' => $vendors->lastItem(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve vendors',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create vendor baru
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:10|unique:vendors',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'address' => 'required|string',
                'city' => 'required|string|max:100',
                'province' => 'required|string|max:100',
                'postal_code' => 'nullable|string|max:10',
                'phone' => 'nullable|string|max:20',
                'fax' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'website' => 'nullable|url|max:255',
                'tax_number' => 'nullable|string|max:50',
                'business_license' => 'nullable|string|max:100',
                'vendor_type' => 'required|in:supplier,distributor,manufacturer,other',
                'contact_person_name' => 'nullable|string|max:255',
                'contact_person_phone' => 'nullable|string|max:20',
                'contact_person_email' => 'nullable|email|max:255',
                'contact_person_position' => 'nullable|string|max:100',
                'payment_terms_days' => 'nullable|integer|min:0|max:365',
                'payment_method' => 'nullable|in:cash,transfer,check,credit',
                'credit_limit' => 'nullable|numeric|min:0',
                'status' => 'nullable|in:active,inactive,blacklist',
                'rating' => 'nullable|numeric|between:1,5',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create vendor
            $vendor = Vendor::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Vendor created successfully',
                'data' => [
                    'vendor' => [
                        'id' => $vendor->id,
                        'code' => $vendor->code,
                        'name' => $vendor->name,
                        'vendor_type' => $vendor->vendor_type_label,
                        'address' => $vendor->full_address,
                        'contact_person' => $vendor->contact_person_full,
                        'payment_terms' => $vendor->payment_terms_days . ' days',
                        'status' => $vendor->status_label,
                        'created_at' => $vendor->created_at,
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create vendor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detail vendor
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $vendor = Vendor::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Vendor retrieved successfully',
                'data' => [
                    'vendor' => [
                        'id' => $vendor->id,
                        'code' => $vendor->code,
                        'name' => $vendor->name,
                        'description' => $vendor->description,
                        'vendor_type' => $vendor->vendor_type,
                        'vendor_type_label' => $vendor->vendor_type_label,
                        'address' => [
                            'street' => $vendor->address,
                            'city' => $vendor->city,
                            'province' => $vendor->province,
                            'postal_code' => $vendor->postal_code,
                            'full_address' => $vendor->full_address,
                        ],
                        'contact' => [
                            'phone' => $vendor->phone,
                            'fax' => $vendor->fax,
                            'email' => $vendor->email,
                            'website' => $vendor->website,
                        ],
                        'legal_info' => [
                            'tax_number' => $vendor->tax_number,
                            'business_license' => $vendor->business_license,
                        ],
                        'contact_person' => [
                            'name' => $vendor->contact_person_name,
                            'phone' => $vendor->contact_person_phone,
                            'email' => $vendor->contact_person_email,
                            'position' => $vendor->contact_person_position,
                            'full' => $vendor->contact_person_full,
                        ],
                        'payment_info' => [
                            'terms_days' => $vendor->payment_terms_days,
                            'method' => $vendor->payment_method,
                            'method_label' => $vendor->payment_method_label,
                            'credit_limit' => $vendor->credit_limit,
                        ],
                        'performance' => [
                            'rating' => $vendor->rating,
                            'rating_stars' => $vendor->rating_stars,
                            'is_reliable' => $vendor->isReliable(),
                            'summary' => $vendor->getPerformanceSummary(),
                        ],
                        'status' => $vendor->status,
                        'status_label' => $vendor->status_label,
                        'notes' => $vendor->notes,
                        'recent_transactions' => $vendor->getRecentTransactions(5),
                        'created_at' => $vendor->created_at,
                        'updated_at' => $vendor->updated_at,
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve vendor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update vendor
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $vendor = Vendor::findOrFail($id);

            // Validasi input
            $validator = Validator::make($request->all(), [
                'code' => 'sometimes|required|string|max:10|unique:vendors,code,' . $vendor->id,
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'address' => 'sometimes|required|string',
                'city' => 'sometimes|required|string|max:100',
                'province' => 'sometimes|required|string|max:100',
                'postal_code' => 'nullable|string|max:10',
                'phone' => 'nullable|string|max:20',
                'fax' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'website' => 'nullable|url|max:255',
                'tax_number' => 'nullable|string|max:50',
                'business_license' => 'nullable|string|max:100',
                'vendor_type' => 'sometimes|required|in:supplier,distributor,manufacturer,other',
                'contact_person_name' => 'nullable|string|max:255',
                'contact_person_phone' => 'nullable|string|max:20',
                'contact_person_email' => 'nullable|email|max:255',
                'contact_person_position' => 'nullable|string|max:100',
                'payment_terms_days' => 'nullable|integer|min:0|max:365',
                'payment_method' => 'nullable|in:cash,transfer,check,credit',
                'credit_limit' => 'nullable|numeric|min:0',
                'status' => 'nullable|in:active,inactive,blacklist',
                'rating' => 'nullable|numeric|between:1,5',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update vendor
            $vendor->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Vendor updated successfully',
                'data' => [
                    'vendor' => [
                        'id' => $vendor->id,
                        'code' => $vendor->code,
                        'name' => $vendor->name,
                        'vendor_type' => $vendor->vendor_type_label,
                        'address' => $vendor->full_address,
                        'contact_person' => $vendor->contact_person_full,
                        'payment_terms' => $vendor->payment_terms_days . ' days',
                        'rating' => $vendor->rating,
                        'status' => $vendor->status_label,
                        'updated_at' => $vendor->updated_at,
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update vendor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete vendor
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $vendor = Vendor::findOrFail($id);

            // Check apakah vendor masih punya transaksi aktif
            if ($vendor->transactions()->whereIn('status', ['draft', 'pending', 'approved'])->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete vendor with active transactions'
                ], 400);
            }

            // Soft delete vendor
            $vendor->delete();

            return response()->json([
                'success' => true,
                'message' => 'Vendor deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete vendor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get vendor performance summary
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function performance($id): JsonResponse
    {
        try {
            $vendor = Vendor::findOrFail($id);
            
            $performance = $vendor->getPerformanceSummary();

            return response()->json([
                'success' => true,
                'message' => 'Vendor performance retrieved successfully',
                'data' => [
                    'vendor' => [
                        'id' => $vendor->id,
                        'name' => $vendor->name,
                        'code' => $vendor->code,
                    ],
                    'performance' => $performance,
                    'recent_transactions' => $vendor->getRecentTransactions(10),
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve vendor performance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update vendor rating
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateRating(Request $request, $id): JsonResponse
    {
        try {
            $vendor = Vendor::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'rating' => 'required|numeric|between:1,5',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $vendor->update([
                'rating' => $request->rating,
                'notes' => $request->notes ?? $vendor->notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Vendor rating updated successfully',
                'data' => [
                    'vendor' => [
                        'id' => $vendor->id,
                        'name' => $vendor->name,
                        'rating' => $vendor->rating,
                        'rating_stars' => $vendor->rating_stars,
                        'is_reliable' => $vendor->isReliable(),
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update vendor rating',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get vendors untuk dropdown/lookup
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function lookup(Request $request): JsonResponse
    {
        try {
            $query = Vendor::active()->notBlacklisted();

            // Filter by vendor type if specified
            if ($request->has('vendor_type')) {
                $query->byType($request->vendor_type);
            }

            // Search if specified
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            }

            $vendors = $query->orderBy('name')
                           ->limit($request->get('limit', 50))
                           ->get(['id', 'code', 'name', 'vendor_type', 'city', 'rating']);

            $data = $vendors->map(function ($vendor) {
                return [
                    'id' => $vendor->id,
                    'code' => $vendor->code,
                    'name' => $vendor->name,
                    'label' => "{$vendor->code} - {$vendor->name}",
                    'vendor_type' => $vendor->vendor_type,
                    'city' => $vendor->city,
                    'rating' => $vendor->rating,
                    'is_reliable' => $vendor->isReliable(),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Vendor lookup data retrieved successfully',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve vendor lookup data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}