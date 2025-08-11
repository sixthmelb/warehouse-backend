<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Warehouse;
use App\Models\Vendor;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * TransactionController untuk transaction management system
 * Handle workflow: Draft → Pending → Approved → Executed
 * 
 * Endpoints:
 * - GET /api/v1/transactions (index)
 * - POST /api/v1/transactions (store)
 * - GET /api/v1/transactions/{id} (show)
 * - PUT /api/v1/transactions/{id} (update)
 * - DELETE /api/v1/transactions/{id} (destroy)
 * - POST /api/v1/transactions/{id}/submit (submit for approval)
 * - POST /api/v1/transactions/{id}/approve (approve transaction)
 * - POST /api/v1/transactions/{id}/execute (execute transaction)
 * - POST /api/v1/transactions/{id}/cancel (cancel transaction)
 * 
 * File: app/Http/Controllers/Api/TransactionController.php
 */
class TransactionController extends Controller
{
    /**
     * Get list transactions dengan filtering dan pagination
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Transaction::with(['warehouse', 'vendor', 'createdBy', 'approvedBy']);

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

            // Filter berdasarkan type (IN/OUT)
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filter berdasarkan sub_type
            if ($request->has('sub_type')) {
                $query->where('sub_type', $request->sub_type);
            }

            // Filter berdasarkan status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter berdasarkan vendor
            if ($request->has('vendor_id')) {
                $query->where('vendor_id', $request->vendor_id);
            }

            // Filter berdasarkan date range
            if ($request->has('date_from')) {
                $query->where('transaction_date', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->where('transaction_date', '<=', $request->date_to);
            }

            // Filter transactions yang dibuat oleh user tertentu
            if ($request->has('created_by')) {
                $query->where('created_by', $request->created_by);
            }

            // Filter pending approval untuk manager/admin
            if ($request->boolean('pending_approval')) {
                $query->where('status', 'pending');
            }

            // Search berdasarkan transaction number atau reference
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('transaction_number', 'like', "%{$search}%")
                      ->orWhere('reference_number', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $transactions = $query->paginate($perPage);

            // Transform data
            $transactions->getCollection()->transform(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'transaction_number' => $transaction->transaction_number,
                    'reference_number' => $transaction->reference_number,
                    'type' => $transaction->type,
                    'type_label' => $transaction->type_label,
                    'sub_type' => $transaction->sub_type,
                    'sub_type_label' => $transaction->sub_type_label,
                    'warehouse' => [
                        'id' => $transaction->warehouse->id,
                        'name' => $transaction->warehouse->name,
                        'code' => $transaction->warehouse->code,
                    ],
                    'vendor' => $transaction->vendor ? [
                        'id' => $transaction->vendor->id,
                        'name' => $transaction->vendor->name,
                        'code' => $transaction->vendor->code,
                    ] : null,
                    'transaction_date' => $transaction->transaction_date,
                    'planned_date' => $transaction->planned_date,
                    'executed_date' => $transaction->executed_date,
                    'status' => $transaction->status,
                    'status_label' => $transaction->status_label,
                    'total_amount' => $transaction->total_amount,
                    'total_items' => $transaction->total_items,
                    'total_quantity' => $transaction->total_quantity,
                    'created_by' => [
                        'id' => $transaction->createdBy->id,
                        'name' => $transaction->createdBy->name,
                    ],
                    'approved_by' => $transaction->approvedBy ? [
                        'id' => $transaction->approvedBy->id,
                        'name' => $transaction->approvedBy->name,
                    ] : null,
                    'can_be_approved' => $transaction->can_be_approved,
                    'can_be_executed' => $transaction->can_be_executed,
                    'can_be_cancelled' => $transaction->can_be_cancelled,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Transactions retrieved successfully',
                'data' => $transactions->items(),
                'meta' => [
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'last_page' => $transactions->lastPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create transaction baru
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:IN,OUT',
                'sub_type' => 'required|in:purchase,return_from_customer,adjustment_in,transfer_in,sale,return_to_vendor,adjustment_out,transfer_out,damaged',
                'warehouse_id' => 'required|exists:warehouses,id',
                'vendor_id' => 'nullable|exists:vendors,id',
                'transaction_date' => 'nullable|date',
                'planned_date' => 'nullable|date|after_or_equal:today',
                'reference_number' => 'nullable|string|max:100',
                'recipient_name' => 'nullable|string|max:255',
                'recipient_phone' => 'nullable|string|max:20',
                'delivery_address' => 'nullable|string',
                'delivery_method' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
                'items' => 'nullable|array',
                'items.*.item_id' => 'required_with:items|exists:items,id',
                'items.*.quantity' => 'required_with:items|numeric|min:0.01',
                'items.*.unit_price' => 'nullable|numeric|min:0',
                'items.*.batch_number' => 'nullable|string|max:100',
                'items.*.expiry_date' => 'nullable|date|after:today',
                'items.*.storage_location' => 'nullable|string|max:100',
                'items.*.notes' => 'nullable|string',
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

            // Validasi vendor untuk transaction IN
            if ($request->type === 'IN' && in_array($request->sub_type, ['purchase', 'return_from_customer']) && !$request->vendor_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vendor is required for this transaction type'
                ], 422);
            }

            DB::beginTransaction();
            
            try {
                // Create transaction
                $transactionData = $request->except('items');
                $transactionData['created_by'] = $user->id;
                $transactionData['status'] = 'draft';
                
                $transaction = Transaction::create($transactionData);

                // Create transaction details jika ada items
                if ($request->has('items') && !empty($request->items)) {
                    foreach ($request->items as $index => $itemData) {
                        $item = Item::find($itemData['item_id']);
                        
                        $detailData = [
                            'transaction_id' => $transaction->id,
                            'item_id' => $itemData['item_id'],
                            'quantity' => $itemData['quantity'],
                            'unit' => $item->unit,
                            'unit_price' => $itemData['unit_price'] ?? 0,
                            'batch_number' => $itemData['batch_number'] ?? null,
                            'expiry_date' => $itemData['expiry_date'] ?? null,
                            'storage_location' => $itemData['storage_location'] ?? null,
                            'notes' => $itemData['notes'] ?? null,
                            'line_number' => $index + 1,
                        ];
                        
                        $detail = TransactionDetail::create($detailData);
                    }
                    
                    // Calculate total amount
                    $transaction->calculateTotalAmount();
                }

                DB::commit();

                $transaction->load(['warehouse', 'vendor', 'createdBy', 'transactionDetails.item']);

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction created successfully',
                    'data' => [
                        'transaction' => $this->formatTransactionData($transaction)
                    ]
                ], 201);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detail transaction
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $transaction = Transaction::with([
                'warehouse', 'vendor', 'createdBy', 'approvedBy', 
                'transactionDetails.item.category', 'stockMovements'
            ])->findOrFail($id);

            // Check warehouse access
            $user = Auth::user();
            if (!$user->isAdmin() && !$user->canAccessWarehouse($transaction->warehouse_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this transaction'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaction retrieved successfully',
                'data' => [
                    'transaction' => $this->formatTransactionData($transaction, true)
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update transaction (hanya bisa jika status draft)
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $transaction = Transaction::findOrFail($id);

            // Check status - hanya draft yang bisa diupdate
            if ($transaction->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft transactions can be updated'
                ], 400);
            }

            // Check ownership atau admin
            $user = Auth::user();
            if (!$user->isAdmin() && $transaction->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only update your own transactions'
                ], 403);
            }

            // Validasi input
            $validator = Validator::make($request->all(), [
                'type' => 'sometimes|required|in:IN,OUT',
                'sub_type' => 'sometimes|required|in:purchase,return_from_customer,adjustment_in,transfer_in,sale,return_to_vendor,adjustment_out,transfer_out,damaged',
                'warehouse_id' => 'sometimes|required|exists:warehouses,id',
                'vendor_id' => 'nullable|exists:vendors,id',
                'transaction_date' => 'nullable|date',
                'planned_date' => 'nullable|date|after_or_equal:today',
                'reference_number' => 'nullable|string|max:100',
                'recipient_name' => 'nullable|string|max:255',
                'recipient_phone' => 'nullable|string|max:20',
                'delivery_address' => 'nullable|string',
                'delivery_method' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update transaction
            $transaction->update($request->all());
            $transaction->load(['warehouse', 'vendor', 'createdBy', 'transactionDetails.item']);

            return response()->json([
                'success' => true,
                'message' => 'Transaction updated successfully',
                'data' => [
                    'transaction' => $this->formatTransactionData($transaction)
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete transaction (hanya bisa jika status draft)
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $transaction = Transaction::findOrFail($id);

            // Check status
            if ($transaction->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft transactions can be deleted'
                ], 400);
            }

            // Check ownership atau admin
            $user = Auth::user();
            if (!$user->isAdmin() && $transaction->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only delete your own transactions'
                ], 403);
            }

            // Soft delete transaction
            $transaction->delete();

            return response()->json([
                'success' => true,
                'message' => 'Transaction deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit transaction for approval
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function submit($id): JsonResponse
    {
        try {
            $transaction = Transaction::findOrFail($id);

            // Check ownership atau admin
            $user = Auth::user();
            if (!$user->isAdmin() && $transaction->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only submit your own transactions'
                ], 403);
            }

            $transaction->submitForApproval();

            return response()->json([
                'success' => true,
                'message' => 'Transaction submitted for approval successfully',
                'data' => [
                    'transaction' => [
                        'id' => $transaction->id,
                        'transaction_number' => $transaction->transaction_number,
                        'status' => $transaction->status,
                        'status_label' => $transaction->status_label,
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve transaction (admin/manager only)
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function approve(Request $request, $id): JsonResponse
    {
        try {
            $transaction = Transaction::findOrFail($id);

            // Check role - hanya admin/manager yang bisa approve
            $user = Auth::user();
            if (!$user->isAdmin() && !$user->isManager()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin or manager can approve transactions'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'approval_notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $transaction->approve($user->id, $request->approval_notes);

            return response()->json([
                'success' => true,
                'message' => 'Transaction approved successfully',
                'data' => [
                    'transaction' => [
                        'id' => $transaction->id,
                        'transaction_number' => $transaction->transaction_number,
                        'status' => $transaction->status,
                        'status_label' => $transaction->status_label,
                        'approved_by' => $user->name,
                        'approval_notes' => $transaction->approval_notes,
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Execute transaction (update stock)
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function execute(Request $request, $id): JsonResponse
    {
        try {
            $transaction = Transaction::findOrFail($id);

            // Check role - hanya admin/manager yang bisa execute
            $user = Auth::user();
            if (!$user->isAdmin() && !$user->isManager()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin or manager can execute transactions'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'execution_notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $transaction->execute($request->execution_notes);

            return response()->json([
                'success' => true,
                'message' => 'Transaction executed successfully',
                'data' => [
                    'transaction' => [
                        'id' => $transaction->id,
                        'transaction_number' => $transaction->transaction_number,
                        'status' => $transaction->status,
                        'status_label' => $transaction->status_label,
                        'executed_date' => $transaction->executed_date,
                        'execution_notes' => $transaction->execution_notes,
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to execute transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel transaction
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function cancel(Request $request, $id): JsonResponse
    {
        try {
            $transaction = Transaction::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $transaction->cancel($request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Transaction cancelled successfully',
                'data' => [
                    'transaction' => [
                        'id' => $transaction->id,
                        'transaction_number' => $transaction->transaction_number,
                        'status' => $transaction->status,
                        'status_label' => $transaction->status_label,
                        'cancellation_reason' => $request->reason,
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transactions yang perlu approval
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function pendingApproval(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Hanya admin/manager yang bisa lihat pending approval
            if (!$user->isAdmin() && !$user->isManager()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin or manager can view pending approvals'
                ], 403);
            }

            $warehouseId = $request->get('warehouse_id');
            $transactions = Transaction::getTransactionsRequiringApproval($warehouseId);

            $data = $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'transaction_number' => $transaction->transaction_number,
                    'type_label' => $transaction->type_label,
                    'sub_type_label' => $transaction->sub_type_label,
                    'warehouse_name' => $transaction->warehouse->name,
                    'vendor_name' => $transaction->vendor ? $transaction->vendor->name : null,
                    'total_amount' => $transaction->total_amount,
                    'created_by' => $transaction->createdBy->name,
                    'created_at' => $transaction->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Pending approval transactions retrieved successfully',
                'data' => $data,
                'summary' => [
                    'total_pending' => $data->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pending approval transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format transaction data untuk response
     * 
     * @param Transaction $transaction
     * @param bool $detailed
     * @return array
     */
    private function formatTransactionData($transaction, $detailed = false): array
    {
        $data = [
            'id' => $transaction->id,
            'transaction_number' => $transaction->transaction_number,
            'reference_number' => $transaction->reference_number,
            'type' => $transaction->type,
            'type_label' => $transaction->type_label,
            'sub_type' => $transaction->sub_type,
            'sub_type_label' => $transaction->sub_type_label,
            'warehouse' => [
                'id' => $transaction->warehouse->id,
                'name' => $transaction->warehouse->name,
                'code' => $transaction->warehouse->code,
            ],
            'vendor' => $transaction->vendor ? [
                'id' => $transaction->vendor->id,
                'name' => $transaction->vendor->name,
                'code' => $transaction->vendor->code,
            ] : null,
            'transaction_date' => $transaction->transaction_date,
            'planned_date' => $transaction->planned_date,
            'executed_date' => $transaction->executed_date,
            'status' => $transaction->status,
            'status_label' => $transaction->status_label,
            'total_amount' => $transaction->total_amount,
            'tax_amount' => $transaction->tax_amount,
            'discount_amount' => $transaction->discount_amount,
            'net_amount' => $transaction->getNetAmount(),
            'currency' => $transaction->currency,
            'total_items' => $transaction->total_items,
            'total_quantity' => $transaction->total_quantity,
            'created_by' => [
                'id' => $transaction->createdBy->id,
                'name' => $transaction->createdBy->name,
            ],
            'approved_by' => $transaction->approvedBy ? [
                'id' => $transaction->approvedBy->id,
                'name' => $transaction->approvedBy->name,
            ] : null,
            'notes' => $transaction->notes,
            'approval_notes' => $transaction->approval_notes,
            'execution_notes' => $transaction->execution_notes,
            'can_be_approved' => $transaction->can_be_approved,
            'can_be_executed' => $transaction->can_be_executed,
            'can_be_cancelled' => $transaction->can_be_cancelled,
            'created_at' => $transaction->created_at,
            'updated_at' => $transaction->updated_at,
        ];

        if ($detailed) {
            $data['delivery_info'] = [
                'recipient_name' => $transaction->recipient_name,
                'recipient_phone' => $transaction->recipient_phone,
                'delivery_address' => $transaction->delivery_address,
                'delivery_method' => $transaction->delivery_method,
                'tracking_number' => $transaction->tracking_number,
            ];

            $data['transaction_details'] = $transaction->transactionDetails->map(function ($detail) {
                return [
                    'id' => $detail->id,
                    'line_number' => $detail->line_number,
                    'item' => [
                        'id' => $detail->item->id,
                        'sku' => $detail->item->sku,
                        'name' => $detail->item->name,
                        'category' => $detail->item->category->name,
                        'unit' => $detail->item->unit,
                    ],
                    'quantity' => $detail->quantity,
                    'actual_quantity' => $detail->actual_quantity,
                    'unit_price' => $detail->unit_price,
                    'total_price' => $detail->total_price,
                    'batch_number' => $detail->batch_number,
                    'expiry_date' => $detail->expiry_date,
                    'storage_location' => $detail->storage_location,
                    'condition' => $detail->condition,
                    'qc_passed' => $detail->qc_passed,
                    'notes' => $detail->notes,
                ];
            });

            if ($transaction->status === 'executed') {
                $data['stock_movements'] = $transaction->stockMovements->map(function ($movement) {
                    return [
                        'id' => $movement->id,
                        'item_name' => $movement->item->name,
                        'movement_type' => $movement->movement_type,
                        'quantity_moved' => $movement->quantity_moved,
                        'quantity_before' => $movement->quantity_before,
                        'quantity_after' => $movement->quantity_after,
                        'movement_date' => $movement->movement_date,
                    ];
                });
            }
        }

        return $data;
    }
}