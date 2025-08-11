<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model ItemStock untuk warehouse management system
 * Menyimpan current stock level untuk setiap item di setiap warehouse
 * Tabel ini di-update real-time setiap ada stock movement
 * 
 * Relationships:
 * - BelongsTo: item (barang yang di-stock)
 * - BelongsTo: warehouse (lokasi stock)
 * 
 * File: app/Models/ItemStock.php
 */
class ItemStock extends Model
{
    use HasFactory;
    // NOTE: Tidak menggunakan SoftDeletes karena ini master stock data

    /**
     * Nama tabel di database
     */
    protected $table = 'item_stocks';

    /**
     * Field yang bisa di-mass assignment
     */
    protected $fillable = [
        'item_id',
        'warehouse_id',
        'current_stock',
        'reserved_stock',
        'available_stock',
        'unit',
        'minimum_stock',
        'maximum_stock',
        'reorder_point',
        'reorder_quantity',
        'average_cost',
        'last_cost',
        'total_value',
        'currency',
        'primary_location',
        'storage_zone',
        'storage_locations',
        'total_batches',
        'earliest_expiry',
        'expiring_stock',
        'last_movement_date',
        'last_movement_type',
        'last_movement_quantity',
        'stock_status',
        'reorder_alert',
        'expiry_alert',
        'alert_notes',
        'last_count_date',
        'last_count_quantity',
        'count_variance',
        'next_count_due',
        'metadata',
        'notes',
    ];

    /**
     * Field yang di-cast ke tipe data tertentu
     */
    protected $casts = [
        'item_id' => 'integer',
        'warehouse_id' => 'integer',
        'current_stock' => 'decimal:2',
        'reserved_stock' => 'decimal:2',
        'available_stock' => 'decimal:2',
        'minimum_stock' => 'decimal:2',
        'maximum_stock' => 'decimal:2',
        'reorder_point' => 'decimal:2',
        'reorder_quantity' => 'decimal:2',
        'average_cost' => 'decimal:2',
        'last_cost' => 'decimal:2',
        'total_value' => 'decimal:2',
        'total_batches' => 'integer',
        'earliest_expiry' => 'date',
        'expiring_stock' => 'decimal:2',
        'last_movement_date' => 'datetime',
        'last_movement_quantity' => 'decimal:2',
        'reorder_alert' => 'boolean',
        'expiry_alert' => 'boolean',
        'last_count_date' => 'date',
        'last_count_quantity' => 'decimal:2',
        'count_variance' => 'decimal:2',
        'next_count_due' => 'datetime',
        'storage_locations' => 'array', // JSON field di-cast ke array
        'metadata' => 'array', // JSON field di-cast ke array
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Enum values untuk stock_status
     */
    const STOCK_STATUSES = [
        'normal' => 'Normal',
        'low' => 'Low Stock',
        'out_of_stock' => 'Out of Stock',
        'overstock' => 'Over Stock'
    ];

    /**
     * Relationship: ItemStock belongs to item
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Relationship: ItemStock belongs to warehouse
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Scope: Filter stock dengan status tertentu
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('stock_status', $status);
    }

    /**
     * Scope: Filter stock yang low
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLowStock($query)
    {
        return $query->where('stock_status', 'low');
    }

    /**
     * Scope: Filter stock yang habis
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('stock_status', 'out_of_stock');
    }

    /**
     * Scope: Filter stock dengan reorder alert
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNeedsReorder($query)
    {
        return $query->where('reorder_alert', true);
    }

    /**
     * Scope: Filter stock dengan expiry alert
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpiryAlert($query)
    {
        return $query->where('expiry_alert', true);
    }

    /**
     * Scope: Filter stock berdasarkan warehouse
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
     * Scope: Filter stock dengan current stock > 0
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHasStock($query)
    {
        return $query->where('current_stock', '>', 0);
    }

    /**
     * Accessor: Get stock status dalam bentuk label
     * 
     * @return string
     */
    public function getStockStatusLabelAttribute()
    {
        return self::STOCK_STATUSES[$this->stock_status] ?? $this->stock_status;
    }

    /**
     * Accessor: Get stock utilization percentage
     * 
     * @return float|null
     */
    public function getStockUtilizationAttribute()
    {
        if ($this->maximum_stock && $this->maximum_stock > 0) {
            return round(($this->current_stock / $this->maximum_stock) * 100, 2);
        }
        return null;
    }

    /**
     * Accessor: Get days until next count
     * 
     * @return int|null
     */
    public function getDaysUntilNextCountAttribute()
    {
        if ($this->next_count_due) {
            return now()->diffInDays($this->next_count_due, false);
        }
        return null;
    }

    /**
     * Accessor: Get days since last movement
     * 
     * @return int|null
     */
    public function getDaysSinceLastMovementAttribute()
    {
        if ($this->last_movement_date) {
            return $this->last_movement_date->diffInDays(now());
        }
        return null;
    }

    /**
     * Helper method: Update stock quantity (IN/OUT)
     * 
     * @param float $quantity
     * @param string $type (IN/OUT)
     * @param string $movementType
     * @return void
     */
    public function updateStock($quantity, $type, $movementType = null)
    {
        if ($type === 'IN') {
            $this->current_stock += $quantity;
        } elseif ($type === 'OUT') {
            if ($this->current_stock < $quantity) {
                throw new \Exception("Insufficient stock. Available: {$this->current_stock}, Required: {$quantity}");
            }
            $this->current_stock -= $quantity;
        }

        // Update available stock (current - reserved)
        $this->available_stock = $this->current_stock - $this->reserved_stock;

        // Update last movement info
        $this->last_movement_date = now();
        $this->last_movement_type = $type;
        $this->last_movement_quantity = $quantity;

        // Update stock status
        $this->updateStockStatus();

        // Update total value
        $this->updateTotalValue();

        $this->save();
    }

    /**
     * Helper method: Reserve stock untuk order
     * 
     * @param float $quantity
     * @return bool
     */
    public function reserveStock($quantity)
    {
        if ($this->available_stock < $quantity) {
            throw new \Exception("Insufficient available stock. Available: {$this->available_stock}, Required: {$quantity}");
        }

        $this->reserved_stock += $quantity;
        $this->available_stock -= $quantity;

        return $this->save();
    }

    /**
     * Helper method: Release reserved stock
     * 
     * @param float $quantity
     * @return bool
     */
    public function releaseReservedStock($quantity)
    {
        if ($this->reserved_stock < $quantity) {
            throw new \Exception("Cannot release more than reserved. Reserved: {$this->reserved_stock}, Release: {$quantity}");
        }

        $this->reserved_stock -= $quantity;
        $this->available_stock += $quantity;

        return $this->save();
    }

    /**
     * Helper method: Update stock status berdasarkan current level
     * 
     * @return void
     */
    public function updateStockStatus()
    {
        if ($this->current_stock <= 0) {
            $this->stock_status = 'out_of_stock';
        } elseif ($this->minimum_stock && $this->current_stock <= $this->minimum_stock) {
            $this->stock_status = 'low';
        } elseif ($this->maximum_stock && $this->current_stock >= $this->maximum_stock) {
            $this->stock_status = 'overstock';
        } else {
            $this->stock_status = 'normal';
        }

        // Update reorder alert
        $this->reorder_alert = $this->reorder_point && $this->current_stock <= $this->reorder_point;
    }

    /**
     * Helper method: Update total value berdasarkan average cost
     * 
     * @return void
     */
    public function updateTotalValue()
    {
        $this->total_value = $this->current_stock * $this->average_cost;
    }

    /**
     * Helper method: Update average cost dengan weighted average
     * 
     * @param float $newQuantity
     * @param float $newCost
     * @return void
     */
    public function updateAverageCost($newQuantity, $newCost)
    {
        if ($this->current_stock > 0 && $this->average_cost > 0) {
            // Weighted average calculation
            $totalValue = ($this->current_stock * $this->average_cost) + ($newQuantity * $newCost);
            $totalQuantity = $this->current_stock + $newQuantity;
            $this->average_cost = round($totalValue / $totalQuantity, 2);
        } else {
            // First purchase atau stock kosong
            $this->average_cost = $newCost;
        }

        // Update last cost
        $this->last_cost = $newCost;
    }

    /**
     * Helper method: Perform cycle count
     * 
     * @param float $countedQuantity
     * @param int $userId
     * @param string|null $notes
     * @return array
     */
    public function performCycleCount($countedQuantity, $userId, $notes = null)
    {
        $variance = $countedQuantity - $this->current_stock;
        
        // Update count history
        $this->last_count_date = today();
        $this->last_count_quantity = $countedQuantity;
        $this->count_variance = $variance;

        // Schedule next count (example: monthly)
        $this->next_count_due = now()->addMonth();

        $result = [
            'previous_stock' => $this->current_stock,
            'counted_stock' => $countedQuantity,
            'variance' => $variance,
            'variance_percentage' => $this->current_stock > 0 ? round(($variance / $this->current_stock) * 100, 2) : 0,
            'adjustment_needed' => $variance != 0
        ];

        // Jika ada variance, buat stock movement adjustment
        if ($variance != 0) {
            $this->current_stock = $countedQuantity;
            $this->available_stock = $countedQuantity - $this->reserved_stock;
            
            // Update stock status
            $this->updateStockStatus();
            $this->updateTotalValue();

            // Create stock movement record untuk audit
            StockMovement::create([
                'item_id' => $this->item_id,
                'warehouse_id' => $this->warehouse_id,
                'user_id' => $userId,
                'movement_type' => $variance > 0 ? 'IN' : 'OUT',
                'movement_reason' => 'cycle_count',
                'quantity_before' => $result['previous_stock'],
                'quantity_moved' => abs($variance),
                'quantity_after' => $countedQuantity,
                'unit' => $this->unit,
                'notes' => "Cycle count adjustment: {$notes}",
                'is_approved' => true,
                'approved_by' => $userId,
                'approved_at' => now(),
                'movement_date' => now(),
            ]);
        }

        $this->save();
        return $result;
    }

    /**
     * Helper method: Check apakah item butuh reorder
     * 
     * @return bool
     */
    public function needsReorder()
    {
        return $this->reorder_alert;
    }

    /**
     * Helper method: Get recommended reorder quantity
     * 
     * @return float
     */
    public function getRecommendedReorderQuantity()
    {
        if (!$this->needsReorder()) {
            return 0;
        }

        // Return configured reorder quantity atau calculate berdasarkan consumption
        if ($this->reorder_quantity) {
            return $this->reorder_quantity;
        }

        // Fallback calculation: difference between max and current stock
        if ($this->maximum_stock) {
            return $this->maximum_stock - $this->current_stock;
        }

        // Default: 2x minimum stock
        return ($this->minimum_stock ?? 0) * 2;
    }

    /**
     * Helper method: Get stock summary untuk dashboard
     * 
     * @return array
     */
    public function getStockSummary()
    {
        return [
            'item_name' => $this->item->name,
            'item_sku' => $this->item->sku,
            'warehouse_name' => $this->warehouse->name,
            'current_stock' => $this->current_stock,
            'available_stock' => $this->available_stock,
            'reserved_stock' => $this->reserved_stock,
            'stock_status' => $this->stock_status_label,
            'stock_utilization' => $this->stock_utilization,
            'minimum_stock' => $this->minimum_stock,
            'maximum_stock' => $this->maximum_stock,
            'reorder_point' => $this->reorder_point,
            'needs_reorder' => $this->needsReorder(),
            'recommended_reorder_qty' => $this->getRecommendedReorderQuantity(),
            'total_value' => $this->total_value,
            'average_cost' => $this->average_cost,
            'last_movement_date' => $this->last_movement_date,
            'days_since_last_movement' => $this->days_since_last_movement,
            'earliest_expiry' => $this->earliest_expiry,
            'expiry_alert' => $this->expiry_alert,
            'primary_location' => $this->primary_location,
        ];
    }

    /**
     * Helper method: Update expiry alerts
     * 
     * @return void
     */
    public function updateExpiryAlerts()
    {
        if (!$this->earliest_expiry) {
            $this->expiry_alert = false;
            $this->expiring_stock = 0;
            return;
        }

        $daysUntilExpiry = now()->diffInDays($this->earliest_expiry, false);
        
        // Set alert jika akan expired dalam 30 hari
        $this->expiry_alert = $daysUntilExpiry <= 30;

        // Hitung stock yang akan expired (simplified - bisa dikembangkan dengan batch tracking)
        if ($this->expiry_alert) {
            $this->expiring_stock = $this->current_stock; // Simplified calculation
        } else {
            $this->expiring_stock = 0;
        }
    }

    /**
     * Helper method: Get movement history untuk item ini di warehouse ini
     * 
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getMovementHistory($limit = 10)
    {
        return StockMovement::where('item_id', $this->item_id)
                           ->where('warehouse_id', $this->warehouse_id)
                           ->with(['user', 'transaction'])
                           ->orderBy('movement_date', 'desc')
                           ->limit($limit)
                           ->get();
    }

    /**
     * Static method: Get stock alerts untuk dashboard
     * 
     * @param int|null $warehouseId
     * @return array
     */
    public static function getStockAlerts($warehouseId = null)
    {
        $query = self::with(['item', 'warehouse']);
        
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $alerts = [
            'low_stock' => $query->clone()->lowStock()->count(),
            'out_of_stock' => $query->clone()->outOfStock()->count(),
            'reorder_needed' => $query->clone()->needsReorder()->count(),
            'expiry_alerts' => $query->clone()->expiryAlert()->count(),
        ];

        return $alerts;
    }

    /**
     * Static method: Get stock valuation summary
     * 
     * @param int|null $warehouseId
     * @return array
     */
    public static function getStockValuation($warehouseId = null)
    {
        $query = self::hasStock();
        
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return [
            'total_items' => $query->count(),
            'total_stock_value' => $query->sum('total_value'),
            'total_quantity' => $query->sum('current_stock'),
            'average_value_per_item' => $query->avg('total_value'),
        ];
    }

    /**
     * Static method: Generate stock report
     * 
     * @param int|null $warehouseId
     * @param string|null $status
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function generateStockReport($warehouseId = null, $status = null)
    {
        $query = self::with(['item.category', 'warehouse']);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($status) {
            $query->byStatus($status);
        }

        return $query->orderBy('stock_status')
                    ->orderBy('current_stock')
                    ->get()
                    ->map(function ($stock) {
                        return $stock->getStockSummary();
                    });
    }

    /**
     * Boot method untuk handle events
     */
    protected static function boot()
    {
        parent::boot();

        // Event: Sebelum create stock baru
        static::creating(function ($stock) {
            // Set default values
            if (is_null($stock->current_stock)) {
                $stock->current_stock = 0;
            }
            if (is_null($stock->reserved_stock)) {
                $stock->reserved_stock = 0;
            }
            if (is_null($stock->available_stock)) {
                $stock->available_stock = $stock->current_stock;
            }

            // Copy unit dari item jika tidak diisi
            if (!$stock->unit && $stock->item) {
                $stock->unit = $stock->item->unit;
            }

            // Copy stock levels dari item jika tidak diisi
            if (!$stock->minimum_stock && $stock->item) {
                $stock->minimum_stock = $stock->item->minimum_stock;
            }
            if (!$stock->maximum_stock && $stock->item) {
                $stock->maximum_stock = $stock->item->maximum_stock;
            }
            if (!$stock->reorder_point && $stock->item) {
                $stock->reorder_point = $stock->item->reorder_point;
            }
            if (!$stock->reorder_quantity && $stock->item) {
                $stock->reorder_quantity = $stock->item->reorder_quantity;
            }

            // Set initial stock status
            $stock->updateStockStatus();

            // Set default currency
            if (!$stock->currency) {
                $stock->currency = 'IDR';
            }
        });

        // Event: Sebelum update stock
        static::updating(function ($stock) {
            // Auto update stock status jika current_stock berubah
            if ($stock->isDirty('current_stock')) {
                $stock->updateStockStatus();
                $stock->updateTotalValue();
            }

            // Update expiry alerts
            if ($stock->isDirty('earliest_expiry')) {
                $stock->updateExpiryAlerts();
            }
        });

        // Event: Sebelum delete stock
        static::deleting(function ($stock) {
            // Warning jika masih ada stock
            if ($stock->current_stock > 0) {
                throw new \Exception('Cannot delete stock record with remaining quantity');
            }
        });
    }
}