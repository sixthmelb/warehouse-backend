<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model Transaction untuk warehouse management system - Fixed untuk serah terima
 * Menyimpan header transaksi serah terima barang ke department/user
 * 
 * Workflow: draft → requested → approved → issued → received → completed
 * 
 * File: app/Models/Transaction.php
 */
class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'transactions';

    protected $fillable = [
        'transaction_number',
        'reference_number',
        'type',
        'sub_type',
        'warehouse_id',
        'vendor_name',
        'vendor_contact',
        'recipient_type',
        'recipient_department',
        'recipient_name',
        'recipient_employee_id',
        'recipient_phone',
        'recipient_email',
        'request_number',
        'request_purpose',
        'priority',
        'project_code',
        'project_name',
        'cost_center',
        'created_by',
        'approved_by',
        'issued_by',
        'received_by',
        'transaction_date',
        'requested_date',
        'approved_date',
        'issued_date',
        'received_date',
        'status',
        'is_returnable',
        'return_due_date',
        'return_status',
        'delivery_address',
        'delivery_method',
        'tracking_number',
        'total_estimated_value',
        'total_actual_value',
        'currency',
        'documents',
        'notes',
        'approval_notes',
        'issue_notes',
        'receive_notes',
        'is_emergency',
        'emergency_reason',
        'requires_inspection',
        'metadata',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'created_by' => 'integer',
        'approved_by' => 'integer',
        'issued_by' => 'integer',
        'received_by' => 'integer',
        'transaction_date' => 'date',
        'requested_date' => 'datetime',
        'approved_date' => 'datetime',
        'issued_date' => 'datetime',
        'received_date' => 'datetime',
        'return_due_date' => 'date',
        'total_estimated_value' => 'decimal:2',
        'total_actual_value' => 'decimal:2',
        'is_returnable' => 'boolean',
        'is_emergency' => 'boolean',
        'requires_inspection' => 'boolean',
        'documents' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Enum values untuk type
     */
    const TYPES = [
        'RECEIVE' => 'Penerimaan Barang',
        'ISSUE' => 'Pengeluaran Barang'
    ];

    /**
     * Enum values untuk sub_type
     */
    const SUB_TYPES = [
        // RECEIVE types
        'purchase_receive' => 'Penerimaan Pembelian',
        'return_receive' => 'Penerimaan Return',
        'transfer_receive' => 'Penerimaan Transfer',
        'adjustment_receive' => 'Penerimaan Adjustment',
        
        // ISSUE types
        'department_issue' => 'Pengeluaran ke Department',
        'user_issue' => 'Pengeluaran ke User',
        'project_issue' => 'Pengeluaran Project',
        'maintenance_issue' => 'Pengeluaran Maintenance',
        'return_issue' => 'Pengeluaran Return',
        'transfer_issue' => 'Pengeluaran Transfer',
    ];

    /**
     * Enum values untuk status
     */
    const STATUSES = [
        'draft' => 'Draft',
        'requested' => 'Diminta',
        'approved' => 'Disetujui',
        'issued' => 'Dikeluarkan',
        'received' => 'Diterima',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan'
    ];

    /**
     * Enum values untuk priority
     */
    const PRIORITIES = [
        'low' => 'Rendah',
        'normal' => 'Normal',
        'high' => 'Tinggi',
        'urgent' => 'Mendesak'
    ];

    /**
     * Relationship: Transaction belongs to warehouse
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Relationship: Transaction dibuat oleh user
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Transaction disetujui oleh user
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relationship: Transaction dikeluarkan oleh user
     */
    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Relationship: Transaction diterima oleh user
     */
    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Relationship: Transaction memiliki banyak detail
     */
    public function transactionDetails()
    {
        return $this->hasMany(TransactionDetail::class)->orderBy('line_number');
    }

    /**
     * Relationship: Transaction menghasilkan stock movements
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Scope: Filter transaksi receive (masuk)
     */
    public function scopeReceive($query)
    {
        return $query->where('type', 'RECEIVE');
    }

    /**
     * Scope: Filter transaksi issue (keluar)
     */
    public function scopeIssue($query)
    {
        return $query->where('type', 'ISSUE');
    }

    /**
     * Scope: Filter transaksi berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter transaksi pending approval
     */
    public function scopePendingApproval($query)
    {
        return $query->where('status', 'requested');
    }

    /**
     * Scope: Filter transaksi berdasarkan department penerima
     */
    public function scopeByRecipientDepartment($query, $department)
    {
        return $query->where('recipient_department', $department);
    }

    /**
     * Accessor: Get type label
     */
    public function getTypeLabelAttribute()
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Accessor: Get sub_type label
     */
    public function getSubTypeLabelAttribute()
    {
        return self::SUB_TYPES[$this->sub_type] ?? $this->sub_type;
    }

    /**
     * Accessor: Get status label
     */
    public function getStatusLabelAttribute()
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Accessor: Get priority label
     */
    public function getPriorityLabelAttribute()
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    /**
     * Accessor: Get total items dalam transaksi
     */
    public function getTotalItemsAttribute()
    {
        return $this->transactionDetails()->count();
    }

    /**
     * Accessor: Get total quantity dalam transaksi
     */
    public function getTotalQuantityAttribute()
    {
        return $this->transactionDetails()->sum('requested_quantity');
    }

    /**
     * Accessor: Check apakah transaksi bisa dicancel
     */
    public function getCanBeCancelledAttribute()
    {
        return in_array($this->status, ['draft', 'requested', 'approved']);
    }

    /**
     * Accessor: Check apakah transaksi bisa diissue
     */
    public function getCanBeIssuedAttribute()
    {
        return $this->status === 'approved';
    }

    /**
     * Accessor: Check apakah transaksi bisa diapprove
     */
    public function getCanBeApprovedAttribute()
    {
        return $this->status === 'requested';
    }

    /**
     * Accessor: Check apakah transaksi bisa direceive
     */
    public function getCanBeReceivedAttribute()
    {
        return $this->status === 'issued';
    }

    /**
     * Helper method: Check apakah transaksi adalah receive
     */
    public function isReceive()
    {
        return $this->type === 'RECEIVE';
    }

    /**
     * Helper method: Check apakah transaksi adalah issue
     */
    public function isIssue()
    {
        return $this->type === 'ISSUE';
    }

    /**
     * Helper method: Submit transaction untuk approval
     */
    public function submitForApproval()
    {
        if ($this->status !== 'draft') {
            throw new \Exception('Hanya transaksi draft yang bisa disubmit untuk approval');
        }

        if ($this->transactionDetails()->count() === 0) {
            throw new \Exception('Transaksi harus memiliki minimal satu item');
        }

        return $this->update([
            'status' => 'requested',
            'requested_date' => now()
        ]);
    }

    /**
     * Helper method: Approve transaction
     */
    public function approve($approvedBy, $notes = null)
    {
        if ($this->status !== 'requested') {
            throw new \Exception('Hanya transaksi yang diminta yang bisa diapprove');
        }

        return $this->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_date' => now(),
            'approval_notes' => $notes
        ]);
    }

    /**
     * Helper method: Issue transaction (keluarkan barang)
     */
    public function issue($issuedBy, $notes = null)
    {
        if ($this->status !== 'approved') {
            throw new \Exception('Hanya transaksi yang diapprove yang bisa diissue');
        }

        \DB::beginTransaction();
        try {
            // Update status transaksi
            $this->update([
                'status' => 'issued',
                'issued_by' => $issuedBy,
                'issued_date' => now(),
                'issue_notes' => $notes
            ]);

            // Process stock movement untuk setiap detail
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
     * Helper method: Receive transaction (terima barang)
     */
    public function receive($receivedBy, $notes = null)
    {
        if ($this->status !== 'issued') {
            throw new \Exception('Hanya transaksi yang diissue yang bisa direceive');
        }

        $status = $this->is_returnable ? 'received' : 'completed';

        return $this->update([
            'status' => $status,
            'received_by' => $receivedBy,
            'received_date' => now(),
            'receive_notes' => $notes
        ]);
    }

    /**
     * Helper method: Complete transaction (selesai)
     */
    public function complete($notes = null)
    {
        if (!in_array($this->status, ['received', 'issued'])) {
            throw new \Exception('Transaksi harus dalam status received atau issued untuk bisa diselesaikan');
        }

        return $this->update([
            'status' => 'completed',
            'receive_notes' => $notes
        ]);
    }

    /**
     * Helper method: Cancel transaction
     */
    public function cancel($reason)
    {
        if (!$this->can_be_cancelled) {
            throw new \Exception('Transaksi tidak bisa dibatalkan dalam status saat ini');
        }

        return $this->update([
            'status' => 'cancelled',
            'notes' => ($this->notes ? $this->notes . "\n" : '') . "Dibatalkan: " . $reason
        ]);
    }

    /**
     * Helper method: Process stock movement untuk transaction detail
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
            'minimum_stock' => $detail->item->minimum_stock ?? 0,
            'maximum_stock' => $detail->item->maximum_stock,
            'reorder_point' => $detail->item->reorder_point,
        ]);

        $quantityBefore = $itemStock->current_stock;
        $quantityMoved = $detail->issued_quantity ?? $detail->requested_quantity;

        // Calculate stock baru berdasarkan type transaksi
        if ($this->isReceive()) {
            $quantityAfter = $quantityBefore + $quantityMoved;
        } else { // ISSUE
            // Validasi stock cukup untuk ISSUE transaction
            if ($quantityBefore < $quantityMoved) {
                throw new \Exception("Stock tidak cukup untuk item {$detail->item->name}. Tersedia: {$quantityBefore}, Diminta: {$quantityMoved}");
            }
            $quantityAfter = $quantityBefore - $quantityMoved;
        }

        // Update stock
        $itemStock->updateStock($quantityMoved, $this->type, $this->sub_type);

        // Create stock movement record
        StockMovement::create([
            'item_id' => $detail->item_id,
            'warehouse_id' => $this->warehouse_id,
            'transaction_id' => $this->id,
            'transaction_detail_id' => $detail->id,
            'user_id' => $this->issued_by ?? $this->created_by,
            'movement_type' => $this->type === 'RECEIVE' ? 'IN' : 'OUT',
            'movement_reason' => $this->sub_type,
            'quantity_before' => $quantityBefore,
            'quantity_moved' => $quantityMoved,
            'quantity_after' => $quantityAfter,
            'unit' => $detail->unit,
            'batch_number' => $detail->batch_number,
            'unit_cost' => $detail->unit_cost,
            'total_cost' => $detail->total_cost,
            'reference_number' => $this->reference_number,
            'movement_date' => $this->issued_date ?? now(),
            'is_approved' => true,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_date,
        ]);
    }

    /**
     * Helper method: Calculate total value dari details
     */
    public function calculateTotalValue()
    {
        $totalValue = $this->transactionDetails()->sum('total_cost');
        $this->update(['total_actual_value' => $totalValue]);
    }

    /**
     * Helper method: Generate transaction number
     */
    public static function generateTransactionNumber($type, $warehouseId)
    {
        $warehouse = Warehouse::find($warehouseId);
        $warehouseCode = $warehouse ? $warehouse->code : 'WH';
        $typePrefix = $type === 'RECEIVE' ? 'RCV' : 'ISS'; // Receive/Issue
        $date = date('Ymd');
        
        // Get counter untuk hari ini
        $todayCount = Transaction::where('type', $type)
                                ->where('warehouse_id', $warehouseId)
                                ->whereDate('created_at', today())
                                ->count() + 1;

        return "{$typePrefix}-{$warehouseCode}-{$date}-" . str_pad($todayCount, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Static method: Get transactions requiring approval
     */
    public static function getTransactionsRequiringApproval($warehouseId = null)
    {
        $query = self::with(['warehouse', 'createdBy'])
                    ->pendingApproval()
                    ->orderBy('requested_date');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->get();
    }

    /**
     * Boot method untuk handle events
     */
    protected static function boot()
    {
        parent::boot();

        // Event: Sebelum create transaction baru
        static::creating(function ($transaction) {
            // Auto generate transaction number
            if (empty($transaction->transaction_number)) {
                $transaction->transaction_number = self::generateTransactionNumber(
                    $transaction->type, 
                    $transaction->warehouse_id
                );
            }

            // Set default transaction_date
            if (empty($transaction->transaction_date)) {
                $transaction->transaction_date = today();
            }

            // Set default currency
            if (empty($transaction->currency)) {
                $transaction->currency = 'IDR';
            }
        });

        // Event: Sebelum delete transaction
        static::deleting(function ($transaction) {
            // Tidak boleh delete transaction yang sudah issued/received
            if (in_array($transaction->status, ['issued', 'received', 'completed'])) {
                throw new \Exception('Tidak bisa menghapus transaksi yang sudah diproses');
            }
        });
    }
}