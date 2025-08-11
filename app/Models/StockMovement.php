<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model StockMovement untuk warehouse management system
 * Menyimpan audit trail semua pergerakan stok barang di warehouse
 * Tabel ini penting untuk tracking history dan audit - TIDAK menggunakan soft delete
 * 
 * Relationships:
 * - BelongsTo: item (barang yang bergerak)
 * - BelongsTo: warehouse (lokasi pergerakan)
 * - BelongsTo: transaction (transaksi penyebab movement)
 * - BelongsTo: transactionDetail (detail transaksi spesifik)
 * - BelongsTo: user (user yang melakukan movement)
 * - BelongsTo: approvedBy (user yang approve movement)
 * - BelongsTo: correctedMovement (movement yang dikoreksi)
 * 
 * File: app/Models/StockMovement.php
 */
class StockMovement extends Model
{
    use HasFactory;
    // NOTE: Tidak menggunakan SoftDeletes untuk audit trail

    /**
     * Nama tabel di database
     */
    protected $table = 'stock_movements';

    /**
     * Field yang bisa di-mass assignment
     */
    protected $fillable = [
        'item_id',
        'warehouse_id',
        'transaction_id',
        'transaction_detail_id',
        'user_id',
        'movement_type',
        'movement_reason',
        'quantity_before',
        'quantity_moved',
        'quantity_after',
        'unit',
        'batch_number',
        'serial_number',
        'expiry_date',
        'location_from',
        'location_to',
        'storage_zone',
        'unit_cost',
        'total_cost',
        'currency',
        'reference_number',
        'document_number',
        'notes',
        'is_approved',
        'approved_by',
        'approved_at',
        'is_correction',
        'corrected_movement_id',
        'correction_reason',
        'is_automated',
        'source_system',
        'movement_date',
    ];

    /**
     * Field yang di-cast ke tipe data tertentu
     */
    protected $casts = [
        'item_id' => 'integer',
        'warehouse_id' => 'integer',
        'transaction_id' => 'integer',
        'transaction_detail_id' => 'integer',
        'user_id' => 'integer',
        'approved_by' => 'integer',
        'corrected_movement_id' => 'integer',
        'quantity_before' => 'decimal:2',
        'quantity_moved' => 'decimal:2',
        'quantity_after' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'expiry_date' => 'date',
        'is_approved' => 'boolean',
        'is_correction' => 'boolean',
        'is_automated' => 'boolean',
        'approved_at' => 'datetime',
        'movement_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Enum values untuk movement_type
     */
    const MOVEMENT_TYPES = [
        'IN' => 'Stock In',
        'OUT' => 'Stock Out'
    ];

    /**
     * Enum values untuk movement_reason (dari Transaction sub_type)
     */
    const MOVEMENT_REASONS = [
        'purchase' => 'Pembelian',
        'return_from_customer' => 'Return dari Customer',
        'adjustment_in' => 'Adjustment Masuk',
        'transfer_in' => 'Transfer Masuk',
        'sale' => 'Penjualan',
        'return_to_vendor' => 'Return ke Vendor',
        'adjustment_out' => 'Adjustment Keluar',
        'transfer_out' => 'Transfer Keluar',
        'damaged' => 'Barang Rusak',
        'cycle_count' => 'Cycle Count Adjustment',
        'manual_adjustment' => 'Manual Adjustment'
    ];

    /**
     * Relationship: StockMovement belongs to item
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Relationship: StockMovement belongs to warehouse
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Relationship: StockMovement belongs to transaction (nullable)
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Relationship: StockMovement belongs to transaction detail (nullable)
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function transactionDetail()
    {
        return $this->belongsTo(TransactionDetail::class);
    }

    /**
     * Relationship: StockMovement dilakukan oleh user
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: StockMovement disetujui oleh user
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relationship: StockMovement yang dikoreksi
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function correctedMovement()
    {
        return $this->belongsTo(StockMovement::class, 'corrected_movement_id');
    }

    /**
     * Relationship: Corrections untuk movement ini
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function corrections()
    {
        return $this->hasMany(StockMovement::class, 'corrected_movement_id');
    }

    /**
     * Scope: Filter movement berdasarkan type
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, $type)
    {
        return $query->where('movement_type', $type);
    }

    /**
     * Scope: Filter movement masuk (IN)
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIncoming($query)
    {
        return $query->where('movement_type', 'IN');
    }

    /**
     * Scope: Filter movement keluar (OUT)
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOutgoing($query)
    {
        return $query->where('movement_type', 'OUT');
    }

    /**
     * Scope: Filter movement yang sudah diapprove
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope: Filter movement berdasarkan tanggal range
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('movement_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Filter movement berdasarkan item
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $itemId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByItem($query, $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    /**
     * Scope: Filter movement berdasarkan warehouse
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
     * Scope: Filter movement yang bukan koreksi
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotCorrection($query)
    {
        return $query->where('is_correction', false);
    }

    /**
     * Scope: Filter movement otomatis
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAutomated($query)
    {
        return $query->where('is_automated', true);
    }

    /**
     * Accessor: Get movement type dalam bentuk label
     * 
     * @return string
     */
    public function getMovementTypeLabelAttribute()
    {
        return self::MOVEMENT_TYPES[$this->movement_type] ?? $this->movement_type;
    }

    /**
     * Accessor: Get movement reason dalam bentuk label
     * 
     * @return string
     */
    public function getMovementReasonLabelAttribute()
    {
        return self::MOVEMENT_REASONS[$this->movement_reason] ?? $this->movement_reason;
    }

    /**
     * Accessor: Get variance amount (difference between expected and actual)
     * 
     * @return float|null
     */
    public function getVarianceAmountAttribute()
    {
        if ($this->is_correction && $this->correctedMovement) {
            return $this->quantity_moved - $this->correctedMovement->quantity_moved;
        }
        return null;
    }

    /**
     * Helper method: Check apakah movement adalah incoming
     * 
     * @return bool
     */
    public function isIncoming()
    {
        return $this->movement_type === 'IN';
    }

    /**
     * Helper method: Check apakah movement adalah outgoing
     * 
     * @return bool
     */
    public function isOutgoing()
    {
        return $this->movement_type === 'OUT';
    }

    /**
     * Helper method: Create correction movement
     * 
     * @param float $correctedQuantity
     * @param string $reason
     * @param int $userId
     * @return StockMovement
     */
    public function createCorrection($correctedQuantity, $reason, $userId)
    {
        if ($this->is_correction) {
            throw new \Exception('Cannot create correction for a correction movement');
        }

        $variance = $correctedQuantity - $this->quantity_moved;
        if ($variance == 0) {
            throw new \Exception('No variance found, correction not needed');
        }

        // Calculate new quantities after correction
        $newQuantityAfter = $this->quantity_before + $correctedQuantity;

        return self::create([
            'item_id' => $this->item_id,
            'warehouse_id' => $this->warehouse_id,
            'transaction_id' => $this->transaction_id,
            'transaction_detail_id' => $this->transaction_detail_id,
            'user_id' => $userId,
            'movement_type' => $variance > 0 ? 'IN' : 'OUT',
            'movement_reason' => 'manual_adjustment',
            'quantity_before' => $this->quantity_after,
            'quantity_moved' => abs($variance),
            'quantity_after' => $newQuantityAfter,
            'unit' => $this->unit,
            'batch_number' => $this->batch_number,
            'serial_number' => $this->serial_number,
            'expiry_date' => $this->expiry_date,
            'storage_zone' => $this->storage_zone,
            'unit_cost' => $this->unit_cost,
            'total_cost' => abs($variance) * $this->unit_cost,
            'currency' => $this->currency,
            'reference_number' => $this->reference_number,
            'notes' => "Correction for movement #{$this->id}: {$reason}",
            'is_approved' => true,
            'approved_by' => $userId,
            'approved_at' => now(),
            'is_correction' => true,
            'corrected_movement_id' => $this->id,
            'correction_reason' => $reason,
            'is_automated' => false,
            'movement_date' => now(),
        ]);
    }

    /**
     * Helper method: Get movement summary untuk reporting
     * 
     * @return array
     */
    public function getMovementSummary()
    {
        return [
            'id' => $this->id,
            'movement_date' => $this->movement_date,
            'movement_type' => $this->movement_type_label,
            'movement_reason' => $this->movement_reason_label,
            'item_name' => $this->item->name,
            'item_sku' => $this->item->sku,
            'warehouse_name' => $this->warehouse->name,
            'quantity_before' => $this->quantity_before,
            'quantity_moved' => $this->quantity_moved,
            'quantity_after' => $this->quantity_after,
            'unit' => $this->unit,
            'batch_number' => $this->batch_number,
            'total_cost' => $this->total_cost,
            'user_name' => $this->user->name,
            'is_correction' => $this->is_correction,
            'transaction_number' => $this->transaction->transaction_number ?? null,
        ];
    }

    /**
     * Static method: Generate stock movement untuk transaction detail
     * 
     * @param TransactionDetail $detail
     * @param float $quantityBefore
     * @param float $quantityAfter
     * @return StockMovement
     */
    public static function createFromTransactionDetail($detail, $quantityBefore, $quantityAfter)
    {
        $transaction = $detail->transaction;
        $quantityMoved = $detail->actual_quantity ?? $detail->quantity;

        return self::create([
            'item_id' => $detail->item_id,
            'warehouse_id' => $transaction->warehouse_id,
            'transaction_id' => $transaction->id,
            'transaction_detail_id' => $detail->id,
            'user_id' => $transaction->created_by,
            'movement_type' => $transaction->type,
            'movement_reason' => $transaction->sub_type,
            'quantity_before' => $quantityBefore,
            'quantity_moved' => $quantityMoved,
            'quantity_after' => $quantityAfter,
            'unit' => $detail->unit,
            'batch_number' => $detail->batch_number,
            'serial_number' => $detail->serial_number,
            'expiry_date' => $detail->expiry_date,
            'location_from' => $transaction->isOutgoing() ? $detail->storage_location : null,
            'location_to' => $transaction->isIncoming() ? $detail->storage_location : null,
            'storage_zone' => $detail->storage_zone,
            'unit_cost' => $detail->unit_price,
            'total_cost' => $detail->total_price,
            'currency' => $transaction->currency,
            'reference_number' => $transaction->reference_number,
            'document_number' => $transaction->transaction_number,
            'notes' => $detail->notes,
            'is_approved' => true,
            'approved_by' => $transaction->approved_by,
            'approved_at' => $transaction->executed_date,
            'is_correction' => false,
            'is_automated' => true,
            'source_system' => 'transaction',
            'movement_date' => $transaction->executed_date ?? now(),
        ]);
    }

    /**
     * Boot method untuk handle events
     */
    protected static function boot()
    {
        parent::boot();

        // Event: Sebelum create movement baru
        static::creating(function ($movement) {
            // Set default movement_date jika tidak diisi
            if (empty($movement->movement_date)) {
                $movement->movement_date = now();
            }

            // Set default currency jika tidak diisi
            if (empty($movement->currency)) {
                $movement->currency = 'IDR';
            }

            // Validate quantity logic
            if ($movement->movement_type === 'IN') {
                if ($movement->quantity_after != $movement->quantity_before + $movement->quantity_moved) {
                    throw new \Exception('Invalid quantity calculation for IN movement');
                }
            } else {
                if ($movement->quantity_after != $movement->quantity_before - $movement->quantity_moved) {
                    throw new \Exception('Invalid quantity calculation for OUT movement');
                }
            }
        });

        // NOTE: Tidak ada delete event karena audit trail tidak boleh dihapus
    }
}