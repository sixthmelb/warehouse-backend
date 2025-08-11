<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model Transaction untuk warehouse management system
 * Menyimpan header transaksi barang masuk/keluar warehouse
 * 
 * Relationships:
 * - BelongsTo: warehouse (gudang terkait transaksi)
 * - BelongsTo: vendor (supplier untuk transaksi IN)
 * - BelongsTo: createdBy (user yang membuat transaksi)
 * - BelongsTo: approvedBy (user yang menyetujui transaksi)
 * - HasMany: transactionDetails (detail item dalam transaksi)
 * - HasMany: stockMovements (pergerakan stock akibat transaksi)
 * 
 * File: app/Models/Transaction.php
 */
class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nama tabel di database
     */
    protected $table = 'transactions';

    /**
     * Field yang bisa di-mass assignment
     */
    protected $fillable = [
        'transaction_number',
        'reference_number',
        'type',
        'sub_type',
        'warehouse_id',
        'vendor_id',
        'created_by',
        'approved_by',
        'transaction_date',
        'planned_date',
        'executed_date',
        'status',
        'total_amount',
        'tax_amount',
        'discount_amount',
        'currency',
        'recipient_name',
        'recipient_phone',
        'delivery_address',
        'delivery_method',
        'tracking_number',
        'documents',
        'notes',
        'approval_notes',
        'execution_notes',
        'metadata',
    ];

    /**
     * Field yang di-cast ke tipe data tertentu
     */
    protected $casts = [
        'warehouse_id' => 'integer',
        'vendor_id' => 'integer',
        'created_by' => 'integer',
        'approved_by' => 'integer',
        'transaction_date' => 'date',
        'planned_date' => 'datetime',
        'executed_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'documents' => 'array', // JSON field di-cast ke array
        'metadata' => 'array', // JSON field di-cast ke array
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Enum values untuk type
     */
    const TYPES = [
        'IN' => 'Barang Masuk',
        'OUT' => 'Barang Keluar'
    ];

    /**
     * Enum values untuk sub_type
     */
    const SUB_TYPES = [
        'purchase' => 'Pembelian',
        'return_from_customer' => 'Return dari Customer',
        'adjustment_in' => 'Adjustment Masuk',
        'transfer_in' => 'Transfer Masuk',
        'sale' => 'Penjualan',
        'return_to_vendor' => 'Return ke Vendor',
        'adjustment_out' => 'Adjustment Keluar',
        'transfer_out' => 'Transfer Keluar',
        'damaged' => 'Barang Rusak'
    ];

    /**
     * Enum values untuk status
     */
    const STATUSES = [
        'draft' => 'Draft',
        'pending' => 'Pending Approval',
        'approved' => 'Approved',
        'executed' => 'Executed',
        'cancelled' => 'Cancelled'
    ];

    /**
     * Relationship: Transaction belongs to warehouse
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Relationship: Transaction belongs to vendor (nullable untuk transaksi OUT)
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Relationship: Transaction dibuat oleh user
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Transaction disetujui oleh user
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relationship: Transaction memiliki banyak detail
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactionDetails()
    {
        return $this->hasMany(TransactionDetail::class)->orderBy('line_number');
    }

    /**
     * Relationship: Transaction menghasilkan stock movements
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Scope: Filter transaksi berdasarkan type
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Filter transaksi masuk (IN)
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIncoming($query)
    {
        return $query->where('type', 'IN');
    }

    /**
     * Scope: Filter transaksi keluar (OUT)
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOutgoing($query)
    {
        return $query->where('type', 'OUT');
    }

    /**
     * Scope: Filter transaksi berdasarkan status
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter transaksi yang pending approval
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Filter transaksi berdasarkan warehouse
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $warehouseId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope: Filter transaksi berdasarkan tanggal range
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    /**
     * Accessor: Get type dalam bentuk label
     * 
     * @return string
     */
    public function getTypeLabelAttribute()
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Accessor: Get sub_type dalam bentuk label
     * 
     * @return string
     */
    public function getSubTypeLabelAttribute()
    {
        return self::SUB_TYPES[$this->sub_type] ?? $this->sub_type;
    }

    /**
     * Accessor: Get status dalam bentuk label
     * 
     * @return string
     */
    public function getStatusLabelAttribute()
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Accessor: Get total items dalam transaksi
     * 
     * @return int
     */
    public function getTotalItemsAttribute()
    {
        return $this->transactionDetails()->count();
    }

    /**
     * Accessor: Get total quantity dalam transaksi
     * 
     * @return float
     */
    public function getTotalQuantityAttribute()
    {
        return $this->transactionDetails()->sum('quantity');
    }

    /**
     * Accessor: Check apakah transaksi bisa dicancel
     * 
     * @return bool
     */
    public function getCanBeCancelledAttribute()
    {
        return in_array($this->status, ['draft', 'pending', 'approved']);
    }

    /**
     * Accessor: Check apakah transaksi bisa diexecute
     * 
     * @return bool
     */
    public function getCanBeExecutedAttribute()
    {
        return $this->status === 'approved';
    }

    /**
     * Accessor: Check apakah transaksi bisa diapprove
     * 
     * @return bool
     */
    public function getCanBeApprovedAttribute()
    {
        return $this->status === 'pending';
    }

    /**
     * Helper method: Check apakah transaksi adalah incoming
     * 
     * @return bool
     */
    public function isIncoming()
    {
        return $this->type === 'IN';
    }

    /**
     * Helper method: Check apakah transaksi adalah outgoing
     * 
     * @return bool
     */
    public function isOutgoing()
    {
        return $this->type === 'OUT';
    }

    /**
     * Helper method: Get net amount (total - discount + tax)
     * 
     * @return float
     */
    public function getNetAmount()
    {
        return $this->total_amount - $this->discount_amount + $this->tax_amount;
    }

    /**
     * Helper method: Submit transaction untuk approval
     * 
     * @return bool
     */
    public function submitForApproval()
    {
        if ($this->status !== 'draft') {
            throw new \Exception('Only draft transactions can be submitted for approval');
        }

        if ($this->transactionDetails()->count() === 0) {
            throw new \Exception('Transaction must have at least one item');
        }

        return $this->update(['status' => 'pending']);
    }

    /**
     * Helper method: Approve transaction
     * 
     * @param int $approvedBy
     * @param string|null $notes
     * @return bool
     */
    public function approve($approvedBy, $notes = null)
    {
        if ($this->status !== 'pending') {
            throw new \Exception('Only pending transactions can be approved');
        }

        return $this->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approval_notes' => $notes
        ]);
    }

    /**
     * Helper method: Execute transaction (update stock)
     * 
     * @param string|null $notes
     * @return bool
     */
    public function execute($notes = null)
    {
        if ($this->status !== 'approved') {
            throw new \Exception('Only approved transactions can be executed');
        }

        \DB::beginTransaction();
        try {
            // Update status transaksi
            $this->update([
                'status' => 'executed',
                'executed_date' => now(),
                'execution_notes' => $notes
            ]);

            // Process setiap detail untuk update stock
            foreach ($this->transactionDetails as $detail) {
                $this->processStockMovement($detail);
            }

            \DB::commit();
            return true;

        } catch (\Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    /**
     * Helper method: Cancel transaction
     * 
     * @param string $reason
     * @return bool
     */
    public function cancel($reason)
    {
        if (!$this->can_be_cancelled) {
            throw new \Exception('Transaction cannot be cancelled in current status');
        }

        return $this->update([
            'status' => 'cancelled',
            'notes' => ($this->notes ? $this->notes . "\n" : '') . "Cancelled: " . $reason
        ]);
    }

    /**
     * Helper method: Process stock movement untuk transaction detail
     * 
     * @param TransactionDetail $detail
     * @return void
     */
    protected function processStockMovement($detail)
    {
        // Get atau create stock record
        $itemStock = ItemStock::firstOrCreate([
            'item_id' => $detail->item_id,
            'warehouse_id' => $this->warehouse_id
        ], [
            'current_stock' => 0,
            'available_stock' => 0,
            'reserved_stock' => 0,
            'unit' => $detail->unit,
            'minimum_stock' => $detail->item->minimum_stock,
            'maximum_stock' => $detail->item->maximum_stock,
            'reorder_point' => $detail->item->reorder_point,
            'reorder_quantity' => $detail->item->reorder_quantity,
        ]);

        $quantityBefore = $itemStock->current_stock;
        $quantityMoved = $detail->actual_quantity ?? $detail->quantity;

        // Calculate stock baru
        if ($this->isIncoming()) {
            $quantityAfter = $quantityBefore + $quantityMoved;
        } else {
            // Validasi stock cukup untuk OUT transaction
            if ($quantityBefore < $quantityMoved) {
                throw new \Exception("Insufficient stock for item {$detail->item->name}. Available: {$quantityBefore}, Required: {$quantityMoved}");
            }
            $quantityAfter = $quantityBefore - $quantityMoved;
        }

        // Update stock
        $itemStock->update([
            'current_stock' => $quantityAfter,
            'available_stock' => $quantityAfter - $itemStock->reserved_stock,
            'last_movement_date' => now(),
            'last_movement_type' => $this->type,
            'last_movement_quantity' => $quantityMoved,
        ]);

        // Update stock status
        $itemStock->updateStockStatus();

        // Update average cost untuk IN transaction
        if ($this->isIncoming() && $detail->unit_price) {
            $itemStock->updateAverageCost($quantityMoved, $detail->unit_price);
            $itemStock->updateTotalValue();
            $itemStock->save();
        }

        // Create stock movement record
        StockMovement::create([
            'item_id' => $detail->item_id,
            'warehouse_id' => $this->warehouse_id,
            'transaction_id' => $this->id,
            'transaction_detail_id' => $detail->id,
            'user_id' => $this->created_by,
            'movement_type' => $this->type,
            'movement_reason' => $this->sub_type,
            'quantity_before' => $quantityBefore,
            'quantity_moved' => $quantityMoved,
            'quantity_after' => $quantityAfter,
            'unit' => $detail->unit,
            'batch_number' => $detail->batch_number,
            'serial_number' => $detail->serial_number,
            'expiry_date' => $detail->expiry_date,
            'unit_cost' => $detail->unit_price,
            'total_cost' => $detail->total_price,
            'reference_number' => $this->reference_number,
            'movement_date' => $this->executed_date ?? now(),
            'is_approved' => true,
            'approved_by' => $this->approved_by,
            'approved_at' => now(),
        ]);
    }

    /**
     * Helper method: Calculate total amount dari details
     * 
     * @return void
     */
    public function calculateTotalAmount()
    {
        $totalAmount = $this->transactionDetails()->sum('total_price');
        $this->update(['total_amount' => $totalAmount]);
    }

    /**
     * Helper method: Generate transaction number
     * 
     * @return string
     */
    public static function generateTransactionNumber($type, $warehouseId)
    {
        $warehouse = Warehouse::find($warehouseId);
        $warehouseCode = $warehouse ? $warehouse->code : 'WH';
        $typePrefix = $type === 'IN' ? 'TI' : 'TO'; // Transaction In/Out
        $date = date('Ymd');
        
        // Get counter untuk hari ini
        $todayCount = Transaction::where('type', $type)
                                ->where('warehouse_id', $warehouseId)
                                ->whereDate('created_at', today())
                                ->count() + 1;

        return "{$typePrefix}-{$warehouseCode}-{$date}-" . str_pad($todayCount, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Helper method: Get transaction summary untuk dashboard
     * 
     * @return array
     */
    public function getTransactionSummary()
    {
        return [
            'id' => $this->id,
            'transaction_number' => $this->transaction_number,
            'type' => $this->type_label,
            'sub_type' => $this->sub_type_label,
            'status' => $this->status_label,
            'warehouse_name' => $this->warehouse->name,
            'vendor_name' => $this->vendor->name ?? null,
            'transaction_date' => $this->transaction_date,
            'total_items' => $this->total_items,
            'total_quantity' => $this->total_quantity,
            'total_amount' => $this->total_amount,
            'net_amount' => $this->getNetAmount(),
            'created_by' => $this->createdBy->name,
            'approved_by' => $this->approvedBy->name ?? null,
            'can_be_approved' => $this->can_be_approved,
            'can_be_executed' => $this->can_be_executed,
            'can_be_cancelled' => $this->can_be_cancelled,
        ];
    }

    /**
     * Static method: Get transactions requiring approval
     * 
     * @param int|null $warehouseId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getTransactionsRequiringApproval($warehouseId = null)
    {
        $query = self::with(['warehouse', 'vendor', 'createdBy'])
                    ->pendingApproval()
                    ->orderBy('created_at');

        if ($warehouseId) {
            $query->byWarehouse($warehouseId);
        }

        return $query->get();
    }

    /**
     * Static method: Get dashboard statistics
     * 
     * @param int|null $warehouseId
     * @param string|null $dateRange
     * @return array
     */
    public static function getDashboardStats($warehouseId = null, $dateRange = 'today')
    {
        $query = self::query();

        if ($warehouseId) {
            $query->byWarehouse($warehouseId);
        }

        // Apply date filter
        switch ($dateRange) {
            case 'today':
                $query->whereDate('transaction_date', today());
                break;
            case 'week':
                $query->whereBetween('transaction_date', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('transaction_date', now()->month)
                      ->whereYear('transaction_date', now()->year);
                break;
        }

        return [
            'total_transactions' => $query->count(),
            'incoming_transactions' => $query->clone()->incoming()->count(),
            'outgoing_transactions' => $query->clone()->outgoing()->count(),
            'pending_approval' => $query->clone()->pendingApproval()->count(),
            'total_value' => $query->clone()->where('status', 'executed')->sum('total_amount'),
        ];
    }

    /**
     * Boot method untuk handle events
     */
    protected static function boot()
    {
        parent::boot();

        // Event: Sebelum create transaction baru
        static::creating(function ($transaction) {
            // Auto generate transaction number jika tidak diisi
            if (empty($transaction->transaction_number)) {
                $transaction->transaction_number = self::generateTransactionNumber(
                    $transaction->type, 
                    $transaction->warehouse_id
                );
            }

            // Set default transaction_date jika tidak diisi
            if (empty($transaction->transaction_date)) {
                $transaction->transaction_date = today();
            }

            // Set default currency jika tidak diisi
            if (empty($transaction->currency)) {
                $transaction->currency = 'IDR';
            }
        });

        // Event: Setelah update transaction
        static::updated(function ($transaction) {
            // Log status changes untuk audit
            if ($transaction->wasChanged('status')) {
                \Log::info("Transaction {$transaction->transaction_number} status changed from {$transaction->getOriginal('status')} to {$transaction->status}");
            }
        });

        // Event: Sebelum delete transaction
        static::deleting(function ($transaction) {
            // Tidak boleh delete transaction yang sudah executed
            if ($transaction->status === 'executed') {
                throw new \Exception('Cannot delete executed transaction');
            }

            // Delete related records
            $transaction->transactionDetails()->delete();
            $transaction->stockMovements()->delete();
        });
    }
}