<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model TransactionDetail untuk warehouse management system - Fixed untuk serah terima
 * Menyimpan detail item dalam setiap transaksi serah terima warehouse
 * 
 * File: app/Models/TransactionDetail.php
 */
class TransactionDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'transaction_details';

    protected $fillable = [
        'transaction_id',
        'item_id',
        'requested_quantity',
        'approved_quantity',
        'issued_quantity',
        'received_quantity',
        'unit',
        'unit_weight',
        'batch_number',
        'serial_number',
        'expiry_date',
        'manufacture_date',
        'unit_cost',
        'total_cost',
        'request_reason',
        'needed_date',
        'urgency',
        'usage_purpose',
        'storage_location',
        'storage_zone',
        'storage_rack',
        'storage_level',
        'condition',
        'condition_notes',
        'qc_passed',
        'qc_notes',
        'is_returnable',
        'return_due_date',
        'return_condition_expected',
        'is_returned',
        'actual_return_date',
        'returned_quantity',
        'line_number',
        'notes',
        'custom_attributes',
    ];

    protected $casts = [
        'transaction_id' => 'integer',
        'item_id' => 'integer',
        'requested_quantity' => 'decimal:2',
        'approved_quantity' => 'decimal:2',
        'issued_quantity' => 'decimal:2',
        'received_quantity' => 'decimal:2',
        'unit_weight' => 'decimal:2',
        'expiry_date' => 'date',
        'manufacture_date' => 'date',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'needed_date' => 'date',
        'return_due_date' => 'date',
        'actual_return_date' => 'date',
        'returned_quantity' => 'decimal:2',
        'line_number' => 'integer',
        'qc_passed' => 'boolean',
        'is_returnable' => 'boolean',
        'is_returned' => 'boolean',
        'custom_attributes' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Enum values untuk condition
     */
    const CONDITIONS = [
        'new' => 'Baru',
        'good' => 'Baik',
        'fair' => 'Cukup',
        'damaged' => 'Rusak'
    ];

    /**
     * Enum values untuk urgency
     */
    const URGENCIES = [
        'low' => 'Rendah',
        'normal' => 'Normal',
        'high' => 'Tinggi',
        'critical' => 'Kritis'
    ];

    /**
     * Relationship: TransactionDetail belongs to transaction
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Relationship: TransactionDetail belongs to item
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Relationship: TransactionDetail menghasilkan stock movements
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Scope: Filter detail berdasarkan condition
     */
    public function scopeByCondition($query, $condition)
    {
        return $query->where('condition', $condition);
    }

    /**
     * Scope: Filter detail yang lolos QC
     */
    public function scopeQcPassed($query)
    {
        return $query->where('qc_passed', true);
    }

    /**
     * Scope: Filter detail yang returnable
     */
    public function scopeReturnable($query)
    {
        return $query->where('is_returnable', true);
    }

    /**
     * Scope: Filter detail yang overdue return
     */
    public function scopeOverdueReturn($query)
    {
        return $query->where('is_returnable', true)
                    ->where('is_returned', false)
                    ->where('return_due_date', '<', now());
    }

    /**
     * Accessor: Get condition label
     */
    public function getConditionLabelAttribute()
    {
        return self::CONDITIONS[$this->condition] ?? $this->condition;
    }

    /**
     * Accessor: Get urgency label
     */
    public function getUrgencyLabelAttribute()
    {
        return self::URGENCIES[$this->urgency] ?? $this->urgency;
    }

    /**
     * Accessor: Get quantity variance (approved vs requested)
     */
    public function getApprovalVarianceAttribute()
    {
        if ($this->approved_quantity !== null) {
            return $this->approved_quantity - $this->requested_quantity;
        }
        return null;
    }

    /**
     * Accessor: Get issue variance (issued vs approved)
     */
    public function getIssueVarianceAttribute()
    {
        if ($this->issued_quantity !== null) {
            $baseQuantity = $this->approved_quantity ?? $this->requested_quantity;
            return $this->issued_quantity - $baseQuantity;
        }
        return null;
    }

    /**
     * Accessor: Get receive variance (received vs issued)
     */
    public function getReceiveVarianceAttribute()
    {
        if ($this->received_quantity !== null && $this->issued_quantity !== null) {
            return $this->received_quantity - $this->issued_quantity;
        }
        return null;
    }

    /**
     * Accessor: Get outstanding quantity (yang belum dikembalikan)
     */
    public function getOutstandingQuantityAttribute()
    {
        if ($this->is_returnable && !$this->is_returned) {
            $issuedQty = $this->issued_quantity ?? $this->approved_quantity ?? $this->requested_quantity;
            $returnedQty = $this->returned_quantity ?? 0;
            return $issuedQty - $returnedQty;
        }
        return 0;
    }

    /**
     * Accessor: Get full storage location
     */
    public function getFullStorageLocationAttribute()
    {
        $parts = array_filter([
            $this->storage_location,
            $this->storage_zone,
            $this->storage_rack,
            $this->storage_level
        ]);
        
        return implode(' - ', $parts);
    }

    /**
     * Accessor: Check apakah item akan expired dalam 30 hari
     */
    public function getIsExpiringSoonAttribute()
    {
        if (!$this->expiry_date) {
            return false;
        }
        
        return $this->expiry_date <= now()->addDays(30);
    }

    /**
     * Accessor: Check apakah item sudah expired
     */
    public function getIsExpiredAttribute()
    {
        if (!$this->expiry_date) {
            return false;
        }
        
        return $this->expiry_date < now();
    }

    /**
     * Accessor: Check apakah return overdue
     */
    public function getIsReturnOverdueAttribute()
    {
        if (!$this->is_returnable || $this->is_returned) {
            return false;
        }
        
        return $this->return_due_date && $this->return_due_date < now();
    }

    /**
     * Helper method: Calculate total cost berdasarkan quantity dan unit cost
     */
    public function calculateTotalCost()
    {
        $quantity = $this->issued_quantity ?? $this->approved_quantity ?? $this->requested_quantity;
        if ($quantity && $this->unit_cost) {
            $this->total_cost = $quantity * $this->unit_cost;
        }
    }

    /**
     * Helper method: Set approved quantity
     */
    public function setApprovedQuantity($approvedQuantity, $reason = null)
    {
        $this->approved_quantity = $approvedQuantity;
        
        if ($approvedQuantity != $this->requested_quantity) {
            $variance = $approvedQuantity - $this->requested_quantity;
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . 
                          "Approval variance: {$variance}. Reason: " . ($reason ?? 'No reason provided');
        }

        $this->calculateTotalCost();
    }

    /**
     * Helper method: Set issued quantity
     */
    public function setIssuedQuantity($issuedQuantity, $reason = null)
    {
        $this->issued_quantity = $issuedQuantity;
        
        $baseQuantity = $this->approved_quantity ?? $this->requested_quantity;
        if ($issuedQuantity != $baseQuantity) {
            $variance = $issuedQuantity - $baseQuantity;
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . 
                          "Issue variance: {$variance}. Reason: " . ($reason ?? 'No reason provided');
        }

        $this->calculateTotalCost();
    }

    /**
     * Helper method: Set received quantity
     */
    public function setReceivedQuantity($receivedQuantity, $reason = null)
    {
        $this->received_quantity = $receivedQuantity;
        
        if ($this->issued_quantity && $receivedQuantity != $this->issued_quantity) {
            $variance = $receivedQuantity - $this->issued_quantity;
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . 
                          "Receive variance: {$variance}. Reason: " . ($reason ?? 'No reason provided');
        }
    }

    /**
     * Helper method: Process return
     */
    public function processReturn($returnedQuantity, $condition = 'good', $notes = null)
    {
        if (!$this->is_returnable) {
            throw new \Exception('Item ini tidak dapat dikembalikan');
        }

        if ($this->is_returned) {
            throw new \Exception('Item sudah dikembalikan sebelumnya');
        }

        $outstandingQty = $this->outstanding_quantity;
        if ($returnedQuantity > $outstandingQty) {
            throw new \Exception("Quantity return melebihi outstanding quantity. Outstanding: {$outstandingQty}");
        }

        $this->returned_quantity = $returnedQuantity;
        $this->actual_return_date = now();
        $this->is_returned = ($returnedQuantity >= $outstandingQty);
        
        if ($notes) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Return: " . $notes;
        }

        // Update condition jika ada perubahan
        if ($condition !== $this->condition) {
            $this->condition = $condition;
            $this->condition_notes = "Kondisi saat return: " . $condition;
        }

        return $this->save();
    }

    /**
     * Helper method: Check apakah detail valid untuk issue
     */
    public function isValidForIssue()
    {
        // Basic validation
        if (!$this->item || !$this->approved_quantity || $this->approved_quantity <= 0) {
            return false;
        }

        // Check QC
        if (!$this->qc_passed) {
            return false;
        }

        // Check condition
        if ($this->condition === 'damaged') {
            return false;
        }

        // Check expiry untuk outgoing transaction
        if ($this->transaction->isIssue() && $this->is_expired) {
            return false;
        }

        return true;
    }

    /**
     * Helper method: Get batch info untuk tracking
     */
    public function getBatchInfo()
    {
        return [
            'batch_number' => $this->batch_number,
            'serial_number' => $this->serial_number,
            'manufacture_date' => $this->manufacture_date,
            'expiry_date' => $this->expiry_date,
            'is_expiring_soon' => $this->is_expiring_soon,
            'is_expired' => $this->is_expired,
        ];
    }

    /**
     * Helper method: Generate QR code untuk detail ini
     */
    public function generateQRCode()
    {
        $qrData = [
            'type' => 'transaction_detail',
            'transaction_id' => $this->transaction_id,
            'detail_id' => $this->id,
            'item_id' => $this->item_id,
            'batch_number' => $this->batch_number,
            'quantity' => $this->requested_quantity,
            'url' => url("/api/v1/transactions/{$this->transaction_id}/details/{$this->id}")
        ];

        return base64_encode(json_encode($qrData));
    }

    /**
     * Boot method untuk handle events
     */
    protected static function boot()
    {
        parent::boot();

        // Event: Sebelum create detail baru
        static::creating(function ($detail) {
            // Auto calculate total cost
            $detail->calculateTotalCost();

            // Set line number otomatis jika tidak diisi
            if (!$detail->line_number) {
                $maxLine = TransactionDetail::where('transaction_id', $detail->transaction_id)
                                          ->max('line_number') ?? 0;
                $detail->line_number = $maxLine + 1;
            }

            // Copy unit dari item jika tidak diisi
            if (!$detail->unit && $detail->item) {
                $detail->unit = $detail->item->unit;
            }

            // Set default approved quantity sama dengan requested
            if (!$detail->approved_quantity) {
                $detail->approved_quantity = $detail->requested_quantity;
            }
        });

        // Event: Sebelum update detail
        static::updating(function ($detail) {
            // Recalculate total cost jika quantity atau unit_cost berubah
            if ($detail->isDirty(['requested_quantity', 'approved_quantity', 'issued_quantity', 'unit_cost'])) {
                $detail->calculateTotalCost();
            }
        });

        // Event: Setelah delete detail
        static::deleted(function ($detail) {
            // Recalculate total value di transaction
            if ($detail->transaction) {
                $detail->transaction->calculateTotalValue();
            }
        });
    }
}