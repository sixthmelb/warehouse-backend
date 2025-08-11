<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * TransactionDetailController untuk transaction detail management
 * Handle CRUD operations untuk items dalam transaksi dengan workflow validation
 * 
 * Endpoints:
 * - GET /api/v1/transactions/{transaction}/details (index)
 * - POST /api/v1/transactions/{transaction}/details (store)
 * - GET /api/v1/transactions/{transaction}/details/{detail} (show)
 * - PUT /api/v1/transactions/{transaction}/details/{detail} (update)
 * - DELETE /api/v1/transactions/{transaction}/details/{detail} (destroy)
 * - POST /api/v1/transactions/{transaction}/details/bulk (bulk operations)
 * - PUT /api/v1/transactions/{transaction}/details/{detail}/actual-quantity (updateActualQuantity)
 * - POST /api/v1/transactions/{transaction}/details/{detail}/qc (performQualityCheck)
 * - POST /api/v1/transactions/{transaction}/details/reorder (reorderDetails)
 * 
 * File: app/Http/Controllers/Api/TransactionDetailController.php
 */
class TransactionDetailController extends Controller
{
    /**
     * Get transaction details (items dalam transaksi) dengan filtering dan pagination
     * 
     * @param Transaction $transaction
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Transaction $transaction, Request $request): JsonResponse
    {
        try {
            // Check warehouse access untuk non-admin
            $user = Auth::user();
            if (!$user->isAdmin() && !$user->canAccessWarehouse($transaction->warehouse_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this transaction'
                ], 403);
        }

            $query = $transaction->transactionDetails()->with(['item.category']);

            // Filter berdasarkan item jika ada
            if ($request->has('item_id')) {
                $query->where('item_id', $request->item_id);
            }

            // Filter berdasarkan condition
            if ($request->has('condition')) {
                $query->where('condition', $request->condition);
            }

            // Filter items yang lolos QC
            if ($request->boolean('qc_passed_only')) {
                $query->where('qc_passed', true);
            }

            // Filter yang ada variance
            if ($request->boolean('has_variance')) {
                $query->hasVariance();
            }

            // Filter berdasarkan storage location
            if ($request->has('storage_location')) {
                $query->byStorageLocation($request->storage_location);
            }

            // Filter berdasarkan batch number
            if ($request->has('batch_number')) {
                $query->where('batch_number', 'like', '%' . $request->batch_number . '%');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'line_number');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination atau get all
            if ($request->boolean('paginate', false)) {
                $perPage = $request->get('per_page', 15);
                $details = $query->paginate($perPage);
                
                $details->getCollection()->transform(function ($detail) {
                    return $this->formatDetailData($detail);
                });

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction details retrieved successfully',
                    'data' => $details->items(),
                    'meta' => [
                        'current_page' => $details->currentPage(),
                        'per_page' => $details->perPage(),
                        'total' => $details->total(),
                        'last_page' => $details->lastPage(),
                    ],
                    'transaction' => $this->getTransactionInfo($transaction)
                ], 200);
            } else {
                $details = $query->get();
                $data = $details->map(function ($detail) {
                    return $this->formatDetailData($detail);
                });

                // Summary information
                $summary = $this->calculateDetailsSummary($details);

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction details retrieved successfully',
                    'data' => $data,
                    'summary' => $summary,
                    'transaction' => $this->getTransactionInfo($transaction)
                ], 200);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve transaction details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add item ke transaksi dengan validasi lengkap
     * 
     * @param Transaction $transaction
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Transaction $transaction, Request $request): JsonResponse
    {
        try {
            // Check apakah transaksi bisa dimodifikasi
            if ($transaction->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft transactions can be modified'
                ], 400);
            }

            // Check ownership atau admin
            $user = Auth::user();
            if (!$user->isAdmin() && $transaction->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only modify your own transactions'
                ], 403);
            }

            // Validasi input
            $validator = Validator::make($request->all(), [
                'item_id' => 'required|exists:items,id',
                'quantity' => 'required|numeric|min:0.01',
                'unit_price' => 'nullable|numeric|min:0',
                'unit_weight' => 'nullable|numeric|min:0',
                'batch_number' => 'nullable|string|max:100',
                'serial_number' => 'nullable|string|max:100',
                'expiry_date' => 'nullable|date|after:today',
                'manufacture_date' => 'nullable|date|before_or_equal:today',
                'storage_location' => 'nullable|string|max:100',
                'storage_zone' => 'nullable|string|max:50',
                'storage_rack' => 'nullable|string|max:50',
                'storage_level' => 'nullable|string|max:20',
                'condition' => 'nullable|in:good,damaged,expired,returned',
                'condition_notes' => 'nullable|string|max:500',
                'qc_passed' => 'nullable|boolean',
                'qc_notes' => 'nullable|string|max:500',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
                'tax_percentage' => 'nullable|numeric|min:0|max:100',
                'notes' => 'nullable|string|max:500',
                'custom_attributes' => 'nullable|array',
                'line_number' => 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get item info
            $item = Item::findOrFail($request->item_id);

            // Check apakah item sudah ada di transaksi (untuk mencegah duplikasi)
            $existingDetail = $transaction->transactionDetails()
                                        ->where('item_id', $request->item_id)
                                        ->first();

            if ($existingDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item already exists in this transaction. Use update to modify quantity.',
                    'existing_detail' => [
                        'id' => $existingDetail->id,
                        'line_number' => $existingDetail->line_number,
                        'quantity' => $existingDetail->quantity
                    ]
                ], 400);
            }

            // Additional validations
            $this->validateItemForTransaction($item, $transaction, $request);

            DB::beginTransaction();
            
            try {
                // Prepare data
                $detailData = $request->only([
                    'item_id', 'quantity', 'unit_price', 'unit_weight',
                    'batch_number', 'serial_number', 'expiry_date', 'manufacture_date',
                    'storage_location', 'storage_zone', 'storage_rack', 'storage_level',
                    'condition', 'condition_notes', 'qc_passed', 'qc_notes',
                    'notes', 'custom_attributes'
                ]);

                // Set defaults dan derived values
                $detailData['transaction_id'] = $transaction->id;
                $detailData['unit'] = $item->unit;
                $detailData['unit_price'] = $detailData['unit_price'] ?? $item->purchase_price ?? 0;
                $detailData['condition'] = $detailData['condition'] ?? 'good';
                $detailData['qc_passed'] = $detailData['qc_passed'] ?? true;

                // Auto set line number jika tidak diisi
                if (!$request->line_number) {
                    $maxLineNumber = $transaction->transactionDetails()->max('line_number') ?? 0;
                    $detailData['line_number'] = $maxLineNumber + 1;
                } else {
                    // Validasi line number tidak duplikat
                    $existingLine = $transaction->transactionDetails()
                                              ->where('line_number', $request->line_number)
                                              ->first();
                    if ($existingLine) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Line number already exists'
                        ], 400);
                    }
                    $detailData['line_number'] = $request->line_number;
                }

                // Create detail
                $detail = TransactionDetail::create($detailData);

                // Apply discount dan tax jika ada
                if ($request->has('discount_percentage') && $request->discount_percentage > 0) {
                    $detail->applyDiscount($request->discount_percentage);
                }

                if ($request->has('tax_percentage') && $request->tax_percentage > 0) {
                    $detail->applyTax($request->tax_percentage);
                }

                $detail->save();

                // Recalculate transaction total
                $transaction->calculateTotalAmount();

                DB::commit();

                $detail->load(['item.category']);

                return response()->json([
                    'success' => true,
                    'message' => 'Item added to transaction successfully',
                    'data' => [
                        'detail' => $this->formatDetailData($detail),
                        'transaction_summary' => $this->getTransactionSummary($transaction),
                    ]
                ], 201);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add item to transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detail specific transaction detail dengan complete information
     * 
     * @param Transaction $transaction
     * @param TransactionDetail $detail
     * @return JsonResponse
     */
    public function show(Transaction $transaction, TransactionDetail $detail): JsonResponse
    {
        try {
            // Pastikan detail belongs to transaction
            if ($detail->transaction_id !== $transaction->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction detail not found in this transaction'
                ], 404);
            }

            // Check warehouse access
            $user = Auth::user();
            if (!$user->isAdmin() && !$user->canAccessWarehouse($transaction->warehouse_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this transaction'
                ], 403);
            }

            $detail->load(['item.category', 'stockMovements.user']);

            return response()->json([
                'success' => true,
                'message' => 'Transaction detail retrieved successfully',
                'data' => [
                    'detail' => $this->formatDetailData($detail, true),
                    'picking_label' => $detail->generatePickingLabel(),
                    'qr_code' => $detail->generateQRCode(),
                    'validation_status' => [
                        'is_valid_for_execution' => $detail->isValidForExecution(),
                        'validation_errors' => $this->getValidationErrors($detail),
                    ],
                    'transaction' => $this->getTransactionInfo($transaction),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve transaction detail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update transaction detail dengan complete validation
     * 
     * @param Transaction $transaction
     * @param TransactionDetail $detail
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Transaction $transaction, TransactionDetail $detail, Request $request): JsonResponse
    {
        try {
            // Pastikan detail belongs to transaction
            if ($detail->transaction_id !== $transaction->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction detail not found in this transaction'
                ], 404);
            }

            // Check apakah transaksi bisa dimodifikasi
            if ($transaction->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft transactions can be modified'
                ], 400);
            }

            // Check ownership atau admin
            $user = Auth::user();
            if (!$user->isAdmin() && $transaction->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only modify your own transactions'
                ], 403);
            }

            // Validasi input
            $validator = Validator::make($request->all(), [
                'quantity' => 'sometimes|required|numeric|min:0.01',
                'unit_price' => 'nullable|numeric|min:0',
                'unit_weight' => 'nullable|numeric|min:0',
                'batch_number' => 'nullable|string|max:100',
                'serial_number' => 'nullable|string|max:100',
                'expiry_date' => 'nullable|date|after:today',
                'manufacture_date' => 'nullable|date|before_or_equal:today',
                'storage_location' => 'nullable|string|max:100',
                'storage_zone' => 'nullable|string|max:50',
                'storage_rack' => 'nullable|string|max:50',
                'storage_level' => 'nullable|string|max:20',
                'condition' => 'nullable|in:good,damaged,expired,returned',
                'condition_notes' => 'nullable|string|max:500',
                'qc_passed' => 'nullable|boolean',
                'qc_notes' => 'nullable|string|max:500',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
                'tax_percentage' => 'nullable|numeric|min:0|max:100',
                'notes' => 'nullable|string|max:500',
                'custom_attributes' => 'nullable|array',
                'actual_quantity' => 'nullable|numeric|min:0',
                'variance_reason' => 'nullable|string|max:500',
                'line_number' => 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check line number uniqueness jika berubah
            if ($request->has('line_number') && $request->line_number != $detail->line_number) {
                $existingLine = $transaction->transactionDetails()
                                          ->where('line_number', $request->line_number)
                                          ->where('id', '!=', $detail->id)
                                          ->first();
                if ($existingLine) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Line number already exists'
                    ], 400);
                }
            }

            DB::beginTransaction();
            
            try {
                // Update detail
                $updateData = $request->only([
                    'quantity', 'unit_price', 'unit_weight',
                    'batch_number', 'serial_number', 'expiry_date', 'manufacture_date',
                    'storage_location', 'storage_zone', 'storage_rack', 'storage_level',
                    'condition', 'condition_notes', 'qc_passed', 'qc_notes',
                    'notes', 'custom_attributes', 'variance_reason', 'line_number'
                ]);

                // Handle actual quantity
                if ($request->has('actual_quantity')) {
                    $detail->setActualQuantity($request->actual_quantity, $request->variance_reason);
                }

                $detail->update($updateData);

                // Apply discount dan tax jika ada perubahan
                if ($request->has('discount_percentage')) {
                    $detail->applyDiscount($request->discount_percentage);
                }

                if ($request->has('tax_percentage')) {
                    $detail->applyTax($request->tax_percentage);
                }

                $detail->save();

                // Recalculate transaction total jika ada perubahan harga/quantity
                if ($request->hasAny(['quantity', 'unit_price', 'discount_percentage', 'tax_percentage'])) {
                    $transaction->calculateTotalAmount();
                }

                DB::commit();

                $detail->load(['item.category']);

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction detail updated successfully',
                    'data' => [
                        'detail' => $this->formatDetailData($detail),
                        'transaction_summary' => $this->getTransactionSummary($transaction),
                    ]
                ], 200);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update transaction detail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove item dari transaksi
     * 
     * @param Transaction $transaction
     * @param TransactionDetail $detail
     * @return JsonResponse
     */
    public function destroy(Transaction $transaction, TransactionDetail $detail): JsonResponse
    {
        try {
            // Pastikan detail belongs to transaction
            if ($detail->transaction_id !== $transaction->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction detail not found in this transaction'
                ], 404);
            }

            // Check apakah transaksi bisa dimodifikasi
            if ($transaction->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft transactions can be modified'
                ], 400);
            }

            // Check ownership atau admin
            $user = Auth::user();
            if (!$user->isAdmin() && $transaction->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only modify your own transactions'
                ], 403);
            }

            DB::beginTransaction();
            
            try {
                // Store item info untuk response
                $itemName = $detail->item->name;
                $itemSku = $detail->item->sku;
                $lineNumber = $detail->line_number;

                // Delete detail
                $detail->delete();

                // Reorder line numbers untuk fill gap
                $remainingDetails = $transaction->transactionDetails()
                                               ->where('line_number', '>', $lineNumber)
                                               ->orderBy('line_number')
                                               ->get();

                foreach ($remainingDetails as $remainingDetail) {
                    $remainingDetail->update(['line_number' => $remainingDetail->line_number - 1]);
                }

                // Recalculate transaction total
                $transaction->calculateTotalAmount();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Item '{$itemName} ({$itemSku})' removed from transaction successfully",
                    'data' => [
                        'removed_item' => [
                            'name' => $itemName,
                            'sku' => $itemSku,
                            'line_number' => $lineNumber,
                        ],
                        'transaction_summary' => $this->getTransactionSummary($transaction),
                    ]
                ], 200);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item from transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk operations untuk transaction details
     * 
     * @param Transaction $transaction
     * @param Request $request
     * @return JsonResponse
     */
    public function bulk(Transaction $transaction, Request $request): JsonResponse
    {
        try {
            // Check apakah transaksi bisa dimodifikasi
            if ($transaction->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft transactions can be modified'
                ], 400);
            }

            // Check ownership atau admin
            $user = Auth::user();
            if (!$user->isAdmin() && $transaction->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only modify your own transactions'
                ], 403);
            }

            // Validasi input
            $validator = Validator::make($request->all(), [
                'operation' => 'required|in:add_multiple,update_multiple,delete_multiple,replace_all,update_quantities,apply_discount,apply_qc',
                'items' => 'required|array|min:1',
                'items.*.item_id' => 'required_if:operation,add_multiple,replace_all|exists:items,id',
                'items.*.detail_id' => 'required_if:operation,update_multiple,delete_multiple,update_quantities|exists:transaction_details,id',
                'items.*.quantity' => 'required_unless:operation,delete_multiple|numeric|min:0.01',
                'items.*.unit_price' => 'nullable|numeric|min:0',
                'items.*.batch_number' => 'nullable|string|max:100',
                'items.*.storage_location' => 'nullable|string|max:100',
                'items.*.notes' => 'nullable|string',
                'global_discount' => 'nullable|numeric|min:0|max:100',
                'qc_status' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();
            
            try {
                $results = [];
                $errors = [];

                switch ($request->operation) {
                    case 'add_multiple':
                        [$results, $errors] = $this->bulkAddItems($transaction, $request->items);
                        break;

                    case 'update_multiple':
                        [$results, $errors] = $this->bulkUpdateItems($transaction, $request->items);
                        break;

                    case 'delete_multiple':
                        [$results, $errors] = $this->bulkDeleteItems($transaction, $request->items);
                        break;

                    case 'replace_all':
                        // Delete semua existing details
                        $transaction->transactionDetails()->delete();
                        // Add new items
                        [$results, $errors] = $this->bulkAddItems($transaction, $request->items);
                        break;

                    case 'update_quantities':
                        [$results, $errors] = $this->bulkUpdateQuantities($transaction, $request->items);
                        break;

                    case 'apply_discount':
                        [$results, $errors] = $this->bulkApplyDiscount($transaction, $request->items, $request->global_discount);
                        break;

                    case 'apply_qc':
                        [$results, $errors] = $this->bulkApplyQC($transaction, $request->items, $request->qc_status);
                        break;
                }

                // Recalculate transaction total
                $transaction->calculateTotalAmount();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Bulk operation completed successfully',
                    'data' => [
                        'operation' => $request->operation,
                        'results' => $results,
                        'errors' => $errors,
                        'summary' => [
                            'total_processed' => count($request->items),
                            'successful' => count($results),
                            'failed' => count($errors),
                        ],
                        'transaction_summary' => $this->getTransactionSummary($transaction),
                    ]
                ], 200);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform bulk operation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update actual quantity untuk detail (untuk execution phase)
     * 
     * @param Transaction $transaction
     * @param TransactionDetail $detail
     * @param Request $request
     * @return JsonResponse
     */
    public function updateActualQuantity(Transaction $transaction, TransactionDetail $detail, Request $request): JsonResponse
    {
        try {
            // Pastikan detail belongs to transaction
            if ($detail->transaction_id !== $transaction->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction detail not found in this transaction'
                ], 404);
            }

            // Validasi input
            $validator = Validator::make($request->all(), [
                'actual_quantity' => 'required|numeric|min:0',
                'variance_reason' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Set actual quantity
            $detail->setActualQuantity($request->actual_quantity, $request->variance_reason);
            $detail->save();

            return response()->json([
                'success' => true,
                'message' => 'Actual quantity updated successfully',
                'data' => [
                    'detail' => $this->formatDetailData($detail),
                    'variance_info' => [
                        'planned_quantity' => $detail->quantity,
                        'actual_quantity' => $detail->actual_quantity,
                        'variance' => $detail->quantity_variance,
                        'variance_percentage' => $detail->variance_percentage,
                        'variance_reason' => $detail->variance_reason,
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update actual quantity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Perform quality check untuk detail
     * 
     * @param Transaction $transaction
     * @param TransactionDetail $detail
     * @param Request $request
     * @return JsonResponse
     */
    public function performQualityCheck(Transaction $transaction, TransactionDetail $detail, Request $request): JsonResponse
    {
        try {
            // Pastikan detail belongs to transaction
            if ($detail->transaction_id !== $transaction->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction detail not found in this transaction'
                ], 404);
            }

            // Validasi input
            $validator = Validator::make($request->all(), [
                'qc_passed' => 'required|boolean',
                'qc_notes' => 'nullable|string|max:500',
                'condition' => 'required|in:good,damaged,expired,returned',
                'condition_notes' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update QC info
            $detail->update([
                'qc_passed' => $request->qc_passed,
                'qc_notes' => $request->qc_notes,
                'condition' => $request->condition,
                'condition_notes' => $request->condition_notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Quality check completed successfully',
                'data' => [
                    'detail' => $this->formatDetailData($detail),
                    'qc_result' => [
                        'passed' => $detail->qc_passed,
                        'condition' => $detail->condition,
                        'notes' => $detail->qc_notes,
                        'is_valid_for_execution' => $detail->isValidForExecution(),
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform quality check',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder transaction details berdasarkan line number
     * 
     * @param Transaction $transaction
     * @param Request $request
     * @return JsonResponse
     */
    public function reorderDetails(Transaction $transaction, Request $request): JsonResponse
    {
        try {
            // Check apakah transaksi bisa dimodifikasi
            if ($transaction->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft transactions can be modified'
                ], 400);
            }

            // Check ownership atau admin
            $user = Auth::user();
            if (!$user->isAdmin() && $transaction->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only modify your own transactions'
                ], 403);
            }

            // Validasi input
            $validator = Validator::make($request->all(), [
                'detail_ids' => 'required|array|min:1',
                'detail_ids.*' => 'exists:transaction_details,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validasi bahwa semua detail IDs belong to transaction
            $validDetailIds = $transaction->transactionDetails()->pluck('id')->toArray();
            $invalidIds = array_diff($request->detail_ids, $validDetailIds);
            
            if (!empty($invalidIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some detail IDs do not belong to this transaction',
                    'invalid_ids' => $invalidIds
                ], 400);
            }

            DB::beginTransaction();
            
            try {
                // Update line numbers berdasarkan urutan dalam array
                foreach ($request->detail_ids as $index => $detailId) {
                    TransactionDetail::where('id', $detailId)
                                   ->where('transaction_id', $transaction->id)
                                   ->update(['line_number' => $index + 1]);
                }

                DB::commit();

                // Get updated details
                $reorderedDetails = $transaction->transactionDetails()
                                                ->with(['item'])
                                                ->orderBy('line_number')
                                                ->get();

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction details reordered successfully',
                    'data' => [
                        'details' => $reorderedDetails->map(function ($detail) {
                            return [
                                'id' => $detail->id,
                                'line_number' => $detail->line_number,
                                'item_name' => $detail->item->name,
                                'item_sku' => $detail->item->sku,
                                'quantity' => $detail->quantity,
                            ];
                        })
                    ]
                ], 200);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder transaction details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate transaction detail
     * 
     * @param Transaction $transaction
     * @param TransactionDetail $detail
     * @param Request $request
     * @return JsonResponse
     */
    public function duplicate(Transaction $transaction, TransactionDetail $detail, Request $request): JsonResponse
    {
        try {
            // Pastikan detail belongs to transaction
            if ($detail->transaction_id !== $transaction->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction detail not found in this transaction'
                ], 404);
            }

            // Check apakah transaksi bisa dimodifikasi
            if ($transaction->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft transactions can be modified'
                ], 400);
            }

            // Validasi input (optional overrides)
            $validator = Validator::make($request->all(), [
                'quantity' => 'nullable|numeric|min:0.01',
                'batch_number' => 'nullable|string|max:100',
                'storage_location' => 'nullable|string|max:100',
                'notes' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();
            
            try {
                // Create duplicate dengan attributes yang sama
                $duplicateData = $detail->toArray();
                
                // Remove ID dan timestamps
                unset($duplicateData['id'], $duplicateData['created_at'], $duplicateData['updated_at']);
                
                // Override dengan input baru jika ada
                if ($request->has('quantity')) {
                    $duplicateData['quantity'] = $request->quantity;
                }
                if ($request->has('batch_number')) {
                    $duplicateData['batch_number'] = $request->batch_number;
                }
                if ($request->has('storage_location')) {
                    $duplicateData['storage_location'] = $request->storage_location;
                }
                if ($request->has('notes')) {
                    $duplicateData['notes'] = $request->notes;
                }
                
                // Set new line number
                $maxLineNumber = $transaction->transactionDetails()->max('line_number');
                $duplicateData['line_number'] = $maxLineNumber + 1;
                
                // Create duplicate
                $duplicateDetail = TransactionDetail::create($duplicateData);
                
                // Recalculate transaction total
                $transaction->calculateTotalAmount();

                DB::commit();

                $duplicateDetail->load(['item.category']);

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction detail duplicated successfully',
                    'data' => [
                        'original_detail_id' => $detail->id,
                        'duplicate_detail' => $this->formatDetailData($duplicateDetail),
                        'transaction_summary' => $this->getTransactionSummary($transaction),
                    ]
                ], 201);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate transaction detail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get validation errors untuk detail
     */
    private function getValidationErrors($detail)
    {
        $errors = [];

        if (!$detail->item) {
            $errors[] = 'Item not found';
        }

        if (!$detail->quantity || $detail->quantity <= 0) {
            $errors[] = 'Invalid quantity';
        }

        if (!$detail->qc_passed) {
            $errors[] = 'Quality check not passed';
        }

        if (in_array($detail->condition, ['damaged', 'expired'])) {
            $errors[] = 'Item condition is not acceptable';
        }

        if ($detail->transaction->isOutgoing() && $detail->is_expired) {
            $errors[] = 'Item is expired';
        }

        return $errors;
    }

    /**
     * Validate item untuk transaction
     */
    private function validateItemForTransaction($item, $transaction, $request)
    {
        // Check item status
        if (!$item->isActive()) {
            throw new \Exception('Item is not active');
        }

        // Check item storage type compatibility
        if ($transaction->warehouse && $transaction->warehouse->isUnderMaintenance()) {
            throw new \Exception('Warehouse is under maintenance');
        }

        // For outgoing transactions, check stock availability
        if ($transaction->isOutgoing()) {
            $availableStock = $item->getAvailableStockInWarehouse($transaction->warehouse_id);
            if ($availableStock < $request->quantity) {
                throw new \Exception("Insufficient stock. Available: {$availableStock}, Required: {$request->quantity}");
            }
        }

        // Check expiry date untuk incoming transactions
        if ($transaction->isIncoming() && $request->expiry_date) {
            $expiryDate = \Carbon\Carbon::parse($request->expiry_date);
            if ($expiryDate->isPast()) {
                throw new \Exception('Expiry date cannot be in the past');
            }
        }
    }

    /**
     * Bulk add items
     */
    private function bulkAddItems($transaction, $items)
    {
        $results = [];
        $errors = [];
        $maxLineNumber = $transaction->transactionDetails()->max('line_number') ?? 0;

        foreach ($items as $index => $itemData) {
            try {
                $item = Item::find($itemData['item_id']);
                
                // Check duplicate
                if ($transaction->transactionDetails()->where('item_id', $itemData['item_id'])->exists()) {
                    $errors[] = [
                        'item_id' => $itemData['item_id'],
                        'error' => 'Item already exists in transaction'
                    ];
                    continue;
                }
                
                $detailData = [
                    'transaction_id' => $transaction->id,
                    'item_id' => $itemData['item_id'],
                    'quantity' => $itemData['quantity'],
                    'unit' => $item->unit,
                    'unit_price' => $itemData['unit_price'] ?? $item->purchase_price ?? 0,
                    'batch_number' => $itemData['batch_number'] ?? null,
                    'storage_location' => $itemData['storage_location'] ?? null,
                    'notes' => $itemData['notes'] ?? null,
                    'line_number' => $maxLineNumber + $index + 1,
                    'condition' => 'good',
                    'qc_passed' => true,
                ];

                $detail = TransactionDetail::create($detailData);
                
                $results[] = [
                    'item_id' => $itemData['item_id'],
                    'item_name' => $item->name,
                    'detail_id' => $detail->id,
                    'line_number' => $detail->line_number,
                    'status' => 'success'
                ];

            } catch (\Exception $e) {
                $errors[] = [
                    'item_id' => $itemData['item_id'] ?? null,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [$results, $errors];
    }

    /**
     * Bulk update items
     */
    private function bulkUpdateItems($transaction, $items)
    {
        $results = [];
        $errors = [];

        foreach ($items as $itemData) {
            try {
                $detail = TransactionDetail::where('id', $itemData['detail_id'])
                                         ->where('transaction_id', $transaction->id)
                                         ->first();

                if (!$detail) {
                    $errors[] = [
                        'detail_id' => $itemData['detail_id'],
                        'error' => 'Detail not found'
                    ];
                    continue;
                }

                $updateData = array_intersect_key($itemData, array_flip([
                    'quantity', 'unit_price', 'batch_number', 'storage_location', 'notes'
                ]));

                $detail->update($updateData);

                $results[] = [
                    'detail_id' => $detail->id,
                    'item_name' => $detail->item->name,
                    'status' => 'success'
                ];

            } catch (\Exception $e) {
                $errors[] = [
                    'detail_id' => $itemData['detail_id'] ?? null,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [$results, $errors];
    }

    /**
     * Bulk delete items
     */
    private function bulkDeleteItems($transaction, $items)
    {
        $results = [];
        $errors = [];

        foreach ($items as $itemData) {
            try {
                $detail = TransactionDetail::where('id', $itemData['detail_id'])
                                         ->where('transaction_id', $transaction->id)
                                         ->first();

                if (!$detail) {
                    $errors[] = [
                        'detail_id' => $itemData['detail_id'],
                        'error' => 'Detail not found'
                    ];
                    continue;
                }

                $itemName = $detail->item->name;
                $lineNumber = $detail->line_number;
                
                $detail->delete();

                $results[] = [
                    'detail_id' => $itemData['detail_id'],
                    'item_name' => $itemName,
                    'line_number' => $lineNumber,
                    'status' => 'success'
                ];

            } catch (\Exception $e) {
                $errors[] = [
                    'detail_id' => $itemData['detail_id'] ?? null,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [$results, $errors];
    }

    /**
     * Bulk update quantities
     */
    private function bulkUpdateQuantities($transaction, $items)
    {
        $results = [];
        $errors = [];

        foreach ($items as $itemData) {
            try {
                $detail = TransactionDetail::where('id', $itemData['detail_id'])
                                         ->where('transaction_id', $transaction->id)
                                         ->first();

                if (!$detail) {
                    $errors[] = [
                        'detail_id' => $itemData['detail_id'],
                        'error' => 'Detail not found'
                    ];
                    continue;
                }

                $oldQuantity = $detail->quantity;
                $detail->update(['quantity' => $itemData['quantity']]);

                $results[] = [
                    'detail_id' => $detail->id,
                    'item_name' => $detail->item->name,
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $detail->quantity,
                    'status' => 'success'
                ];

            } catch (\Exception $e) {
                $errors[] = [
                    'detail_id' => $itemData['detail_id'] ?? null,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [$results, $errors];
    }

    /**
     * Bulk apply discount
     */
    private function bulkApplyDiscount($transaction, $items, $globalDiscount = null)
    {
        $results = [];
        $errors = [];

        foreach ($items as $itemData) {
            try {
                $detail = TransactionDetail::where('id', $itemData['detail_id'])
                                         ->where('transaction_id', $transaction->id)
                                         ->first();

                if (!$detail) {
                    $errors[] = [
                        'detail_id' => $itemData['detail_id'],
                        'error' => 'Detail not found'
                    ];
                    continue;
                }

                $discountPercentage = $globalDiscount ?? $itemData['discount_percentage'] ?? 0;
                $detail->applyDiscount($discountPercentage);
                $detail->save();

                $results[] = [
                    'detail_id' => $detail->id,
                    'item_name' => $detail->item->name,
                    'discount_applied' => $discountPercentage,
                    'discount_amount' => $detail->discount_amount,
                    'status' => 'success'
                ];

            } catch (\Exception $e) {
                $errors[] = [
                    'detail_id' => $itemData['detail_id'] ?? null,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [$results, $errors];
    }

    /**
     * Bulk apply QC status
     */
    private function bulkApplyQC($transaction, $items, $qcStatus)
    {
        $results = [];
        $errors = [];

        foreach ($items as $itemData) {
            try {
                $detail = TransactionDetail::where('id', $itemData['detail_id'])
                                         ->where('transaction_id', $transaction->id)
                                         ->first();

                if (!$detail) {
                    $errors[] = [
                        'detail_id' => $itemData['detail_id'],
                        'error' => 'Detail not found'
                    ];
                    continue;
                }

                $detail->update([
                    'qc_passed' => $qcStatus ?? $itemData['qc_passed'] ?? true,
                    'qc_notes' => $itemData['qc_notes'] ?? null,
                ]);

                $results[] = [
                    'detail_id' => $detail->id,
                    'item_name' => $detail->item->name,
                    'qc_status' => $detail->qc_passed ? 'Passed' : 'Failed',
                    'status' => 'success'
                ];

            } catch (\Exception $e) {
                $errors[] = [
                    'detail_id' => $itemData['detail_id'] ?? null,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [$results, $errors];
    }

    /**
     * Calculate details summary
     */
    private function calculateDetailsSummary($details)
    {
        return [
            'total_lines' => $details->count(),
            'total_quantity' => $details->sum('quantity'),
            'total_amount' => $details->sum('total_price'),
            'total_discount' => $details->sum('discount_amount'),
            'total_tax' => $details->sum('tax_amount'),
            'net_amount' => $details->sum('net_total_price'),
            'lines_with_variance' => $details->filter(function ($detail) {
                return $detail->quantity_variance !== null && $detail->quantity_variance != 0;
            })->count(),
            'qc_passed_lines' => $details->where('qc_passed', true)->count(),
            'qc_failed_lines' => $details->where('qc_passed', false)->count(),
            'unique_items' => $details->pluck('item_id')->unique()->count(),
            'items_by_condition' => [
                'good' => $details->where('condition', 'good')->count(),
                'damaged' => $details->where('condition', 'damaged')->count(),
                'expired' => $details->where('condition', 'expired')->count(),
                'returned' => $details->where('condition', 'returned')->count(),
            ],
        ];
    }

    /**
     * Get transaction info
     */
    private function getTransactionInfo($transaction)
    {
        return [
            'id' => $transaction->id,
            'transaction_number' => $transaction->transaction_number,
            'type' => $transaction->type,
            'type_label' => $transaction->type_label,
            'sub_type' => $transaction->sub_type,
            'sub_type_label' => $transaction->sub_type_label,
            'status' => $transaction->status,
            'status_label' => $transaction->status_label,
            'can_modify' => $transaction->status === 'draft',
            'can_execute' => $transaction->can_be_executed,
            'warehouse_name' => $transaction->warehouse->name,
            'vendor_name' => $transaction->vendor->name ?? null,
        ];
    }

    /**
     * Get transaction summary
     */
    private function getTransactionSummary($transaction)
    {
        return [
            'total_items' => $transaction->total_items,
            'total_quantity' => $transaction->total_quantity,
            'total_amount' => $transaction->total_amount,
            'tax_amount' => $transaction->tax_amount,
            'discount_amount' => $transaction->discount_amount,
            'net_amount' => $transaction->getNetAmount(),
        ];
    }

    /**
     * Format transaction detail data untuk response
     */
    private function formatDetailData($detail, $detailed = false)
    {
        $data = [
            'id' => $detail->id,
            'line_number' => $detail->line_number,
            'item' => [
                'id' => $detail->item->id,
                'sku' => $detail->item->sku,
                'name' => $detail->item->name,
                'brand' => $detail->item->brand,
                'category' => $detail->item->category->name,
                'unit' => $detail->item->unit,
                'image_url' => $detail->item->image_url,
            ],
            'quantities' => [
                'quantity' => $detail->quantity,
                'actual_quantity' => $detail->actual_quantity,
                'unit' => $detail->unit,
                'unit_weight' => $detail->unit_weight,
                'variance' => $detail->quantity_variance,
                'variance_percentage' => $detail->variance_percentage,
            ],
            'pricing' => [
                'unit_price' => $detail->unit_price,
                'total_price' => $detail->total_price,
                'discount_percentage' => $detail->discount_percentage,
                'discount_amount' => $detail->discount_amount,
                'tax_percentage' => $detail->tax_percentage,
                'tax_amount' => $detail->tax_amount,
                'net_unit_price' => $detail->net_unit_price,
                'net_total_price' => $detail->net_total_price,
            ],
            'batch_info' => [
                'batch_number' => $detail->batch_number,
                'serial_number' => $detail->serial_number,
                'expiry_date' => $detail->expiry_date,
                'manufacture_date' => $detail->manufacture_date,
                'is_expiring_soon' => $detail->is_expiring_soon,
                'is_expired' => $detail->is_expired,
            ],
            'storage' => [
                'storage_location' => $detail->storage_location,
                'storage_zone' => $detail->storage_zone,
                'storage_rack' => $detail->storage_rack,
                'storage_level' => $detail->storage_level,
                'full_location' => $detail->full_storage_location,
            ],
            'quality' => [
                'condition' => $detail->condition,
                'condition_label' => $detail->condition_label,
                'condition_notes' => $detail->condition_notes,
                'qc_passed' => $detail->qc_passed,
                'qc_notes' => $detail->qc_notes,
            ],
            'variance_reason' => $detail->variance_reason,
            'notes' => $detail->notes,
            'custom_attributes' => $detail->custom_attributes,
            'is_valid_for_execution' => $detail->isValidForExecution(),
            'created_at' => $detail->created_at,
            'updated_at' => $detail->updated_at,
        ];

        if ($detailed) {
            // Add extra detailed information
            $data['stock_movements'] = $detail->stockMovements->map(function ($movement) {
                return [
                    'id' => $movement->id,
                    'movement_type' => $movement->movement_type,
                    'quantity_moved' => $movement->quantity_moved,
                    'movement_date' => $movement->movement_date,
                    'user_name' => $movement->user->name,
                ];
            });

            $data['picking_info'] = [
                'picking_label' => $detail->generatePickingLabel(),
                'qr_code' => $detail->generateQRCode(),
            ];

            $data['validation_errors'] = $this->getValidationErrors($detail);
        }

        return $data;
    }
}