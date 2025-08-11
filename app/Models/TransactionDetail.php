<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model TransactionDetail untuk warehouse management system
 * Menyimpan detail item dalam setiap transaksi warehouse
 * 
 * Relationships:
 * - BelongsTo: transaction (transaksi induk)
 * - BelongsTo: item (barang yang ditransaksikan)
 * - HasMany: stockMovements (pergerakan stock akibat detail ini)
 * 
 * File: app/Models/TransactionDetail.php
 */
class TransactionDetail extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nama tabel di database
     */
    protected $table = 'transaction_details';

    /**
     * Field yang bisa di-mass assignment
     */
    protected $fillable = [
        'transaction_id',
        'item_id',
        'quantity',
        'unit',
        'unit_weight',
        'batch_number',
        'serial_number',
        'expiry_date',
        'manufacture_date',
        'unit_price',
        'total_price',
        'discount_percentage',
        'discount_amount',
        'tax_percentage',
        'tax_amount',
        'storage_location',
        'storage_zone',
        'storage_rack',
        'storage_level',
        'condition',
        'condition_notes',
        'qc_passed',
        'qc_notes',
        'line_number',
        'actual_quantity',
        'variance_reason',
        'custom_attributes',
        'notes',
    ];

    /**
     * Field yang di-cast ke tipe data tertentu
     */
    protected $casts = [
        'transaction_id' => 'integer',
        'item_id' => 'integer',
        'quantity' => 'decimal:2',
        'unit_weight' => 'decimal:2',
        'expiry_date' => 'date',
        'manufacture_date' => 'date',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_number' => 'integer',
        'actual_quantity' => 'decimal:2',
        'qc_passed' => 'boolean',
        'custom_attributes' => 'array', // JSON field di-cast ke array
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Enum values untuk condition
     */
    const CONDITIONS = [
        'good' => 'Good Condition',
        'damaged' => 'Damaged',
        'expired' => 'Expired',
        'returned' => 'Returned'
    ];

    /**
     * Relationship: TransactionDetail belongs to transaction
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Relationship: TransactionDetail belongs to item
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Relationship: TransactionDetail menghasilkan stock movements
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Scope: Filter detail berdasarkan condition
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $condition
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCondition($query, $condition)
    {
        return $query->where('condition', $condition);
    }

    /**
     * Scope: Filter detail yang lolos QC
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeQcPassed($query)
    {
        return $query->where('qc_passed', true);
    }

    /**
     * Scope: Filter detail yang ada variance
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHasVariance($query)
    {
        return $query->whereNotNull('actual_quantity')
                    ->whereColumn('actual_quantity', '!=', 'quantity');
    }

    /**
     * Scope: Filter detail berdasarkan storage location
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $location
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStorageLocation($query, $location)
    {
        return $query->where('storage_location', 'like', "%{$location}%");
    }

    /**
     * Accessor: Get condition dalam bentuk label
     * 
     * @return string
     */
    public function getConditionLabelAttribute()
    {
        return self::CONDITIONS[$this->condition] ?? $this->condition;
    }

    /**
     * Accessor: Get quantity variance (actual - planned)
     * 
     * @return float|null
     */
    public function getQuantityVarianceAttribute()
    {
        if ($this->actual_quantity !== null) {
            return $this->actual_quantity - $this->quantity;
        }
        return null;
    }

    /**
     * Accessor: Get variance percentage
     * 
     * @return float|null
     */
    public function getVariancePercentageAttribute()
    {
        if ($this->actual_quantity !== null && $this->quantity > 0) {
            return round((($this->actual_quantity - $this->quantity) / $this->quantity) * 100, 2);
        }
        return null;
    }

    /**
     * Accessor: Get net price per unit (setelah discount dan tax)
     * 
     * @return float
     */
    public function getNetUnitPriceAttribute()
    {
        $price = $this->unit_price;
        $price -= $this->discount_amount / $this->quantity; // Discount per unit
        $price += $this->tax_amount / $this->quantity; // Tax per unit
        return round($price, 2);
    }

    /**
     * Accessor: Get net total price (setelah discount dan tax)
     * 
     * @return float
     */
    public function getNetTotalPriceAttribute()
    {
        return $this->total_price - $this->discount_amount + $this->tax_amount;
    }

    /**
     * Accessor: Get full storage location
     * 
     * @return string
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
     * 
     * @return bool
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
     * 
     * @return bool
     */
    public function getIsExpiredAttribute()
    {
        if (!$this->expiry_date) {
            return false;
        }
        
        return $this->expiry_date < now();
    }

    /**
     * Helper method: Calculate total price berdasarkan quantity dan unit price
     * 
     * @return void
     */
    public function calculateTotalPrice()
    {
        if ($this->quantity && $this->unit_price) {
            $this->total_price = $this->quantity * $this->unit_price;
        }
    }

    /**
     * Helper method: Apply discount ke total price
     * 
     * @param float $discountPercentage
     * @return void
     */
    public function applyDiscount($discountPercentage)
    {
        $this->discount_percentage = $discountPercentage;
        $this->discount_amount = ($this->total_price * $discountPercentage) / 100;
    }

    /**
     * Helper method: Apply tax ke total price
     * 
     * @param float $taxPercentage
     * @return void
     */
    public function applyTax($taxPercentage)
    {
        $this->tax_percentage = $taxPercentage;
        $this->tax_amount = (($this->total_price - $this->discount_amount) * $taxPercentage) / 100;
    }

    /**
     * Helper method: Set actual quantity dan variance reason
     * 
     * @param float $actualQuantity
     * @param string|null $reason
     * @return void
     */
    public function setActualQuantity($actualQuantity, $reason = null)
    {
        $this->actual_quantity = $actualQuantity;
        
        if ($actualQuantity != $this->quantity) {
            $this->variance_reason = $reason ?? 'Quantity variance during execution';
        }
    }

    /**
     * Helper method: Check apakah detail ini valid untuk execution
     * 
     * @return bool
     */
    public function isValidForExecution()
    {
        // Basic validation
        if (!$this->item || !$this->quantity || $this->quantity <= 0) {
            return false;
        }

        // Check QC jika required
        if (!$this->qc_passed) {
            return false;
        }

        // Check condition
        if (in_array($this->condition, ['damaged', 'expired'])) {
            return false;
        }

        // Check expiry untuk outgoing transaction
        if ($this->transaction->isOutgoing() && $this->is_expired) {
            return false;
        }

        return true;
    }

    /**
     * Helper method: Get batch info untuk tracking
     * 
     * @return array
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
     * Helper method: Generate label untuk picking/packing
     * 
     * @return array
     */
    public function generatePickingLabel()
    {
        return [
            'transaction_number' => $this->transaction->transaction_number,
            'line_number' => $this->line_number,
            'item_name' => $this->item->name,
            'item_sku' => $this->item->sku,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'batch_number' => $this->batch_number,
            'storage_location' => $this->full_storage_location,
            'special_notes' => $this->notes,
            'qr_code' => $this->generateQRCode(),
        ];
    }

    /**
     * Helper method: Generate QR code untuk detail ini
     * 
     * @return string
     */
    public function generateQRCode()
    {
        $qrData = [
            'type' => 'transaction_detail',
            'transaction_id' => $this->transaction_id,
            'detail_id' => $this->id,
            'item_id' => $this->item_id,
            'batch_number' => $this->batch_number,
            'quantity' => $this->quantity,
            'url' => url("/api/transactions/{$this->transaction_id}/details/{$this->id}")
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
            // Auto calculate total price
            $detail->calculateTotalPrice();

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
        });

        // Event: Sebelum update detail
        static::updating(function ($detail) {
            // Recalculate total price jika quantity atau unit_price berubah
            if ($detail->isDirty(['quantity', 'unit_price'])) {
                $detail->calculateTotalPrice();
            }
        });

        // Event: Setelah delete detail
        static::deleted(function ($detail) {
            // Recalculate total amount di transaction
            if ($detail->transaction) {
                $detail->transaction->calculateTotalAmount();
            }
        });
    }
}