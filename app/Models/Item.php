<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model Item untuk warehouse management system
 * Menyimpan master data barang/item yang ada di warehouse
 * 
 * Relationships:
 * - BelongsTo: category (kategori item)
 * - HasMany: transactionDetails (detail transaksi item ini)
 * - HasMany: stockMovements (pergerakan stock item ini)
 * - HasMany: itemStocks (stock item di berbagai warehouse)
 * 
 * File: app/Models/Item.php
 */
class Item extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nama tabel di database
     */
    protected $table = 'items';

    /**
     * Field yang bisa di-mass assignment
     */
    protected $fillable = [
        'sku',
        'barcode',
        'name',
        'description',
        'category_id',
        'brand',
        'model',
        'weight',
        'length',
        'width',
        'height',
        'color',
        'size',
        'unit',
        'items_per_package',
        'package_type',
        'purchase_price',
        'selling_price',
        'currency',
        'minimum_stock',
        'maximum_stock',
        'reorder_point',
        'reorder_quantity',
        'storage_type',
        'storage_notes',
        'has_expiry',
        'batch_tracking',
        'serial_tracking',
        'status',
        'custom_fields',
        'notes',
        'image_url',
    ];

    /**
     * Field yang di-cast ke tipe data tertentu
     */
    protected $casts = [
        'category_id' => 'integer',
        'weight' => 'decimal:2',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'items_per_package' => 'integer',
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'minimum_stock' => 'integer',
        'maximum_stock' => 'integer',
        'reorder_point' => 'integer',
        'reorder_quantity' => 'integer',
        'has_expiry' => 'boolean',
        'batch_tracking' => 'boolean',
        'serial_tracking' => 'boolean',
        'custom_fields' => 'array', // JSON field di-cast ke array
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Enum values untuk storage_type
     */
    const STORAGE_TYPES = [
        'normal' => 'Normal Storage',
        'cold' => 'Cold Storage',
        'frozen' => 'Frozen Storage',
        'hazardous' => 'Hazardous Storage'
    ];

    /**
     * Enum values untuk status
     */
    const STATUSES = [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'discontinued' => 'Discontinued'
    ];

    /**
     * Relationship: Item belongs to category
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relationship: Item memiliki banyak transaction details
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactionDetails()
    {
        return $this->hasMany(TransactionDetail::class);
    }

    /**
     * Relationship: Item memiliki banyak stock movements
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Relationship: Item memiliki stock di berbagai warehouse
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function itemStocks()
    {
        return $this->hasMany(ItemStock::class);
    }

    /**
     * Relationship: Get warehouses yang memiliki stock item ini
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class, 'item_stocks')
                    ->withPivot(['current_stock', 'available_stock', 'reserved_stock', 'stock_status'])
                    ->withTimestamps();
    }

    /**
     * Scope: Filter item yang aktif
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Filter item berdasarkan kategori
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $categoryId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope: Filter item berdasarkan brand
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $brand
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByBrand($query, $brand)
    {
        return $query->where('brand', 'like', "%{$brand}%");
    }

    /**
     * Scope: Filter item dengan stock rendah
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLowStock($query)
    {
        return $query->whereHas('itemStocks', function ($stockQuery) {
            $stockQuery->where('stock_status', 'low');
        });
    }

    /**
     * Scope: Filter item yang habis
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOutOfStock($query)
    {
        return $query->whereHas('itemStocks', function ($stockQuery) {
            $stockQuery->where('stock_status', 'out_of_stock');
        });
    }

    /**
     * Scope: Filter item dengan batch tracking
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithBatchTracking($query)
    {
        return $query->where('batch_tracking', true);
    }

    /**
     * Scope: Filter item dengan serial tracking
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithSerialTracking($query)
    {
        return $query->where('serial_tracking', true);
    }

    /**
     * Scope: Search item berdasarkan keyword
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $keyword
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('name', 'like', "%{$keyword}%")
              ->orWhere('sku', 'like', "%{$keyword}%")
              ->orWhere('barcode', 'like', "%{$keyword}%")
              ->orWhere('description', 'like', "%{$keyword}%")
              ->orWhere('brand', 'like', "%{$keyword}%");
        });
    }

    /**
     * Accessor: Get storage type dalam bentuk label
     * 
     * @return string
     */
    public function getStorageTypeLabelAttribute()
    {
        return self::STORAGE_TYPES[$this->storage_type] ?? $this->storage_type;
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
     * Accessor: Get dimensi dalam format string
     * 
     * @return string|null
     */
    public function getDimensionsAttribute()
    {
        if ($this->length && $this->width && $this->height) {
            return "{$this->length} x {$this->width} x {$this->height} cm";
        }
        return null;
    }

    /**
     * Accessor: Get volume dalam cm3
     * 
     * @return float|null
     */
    public function getVolumeAttribute()
    {
        if ($this->length && $this->width && $this->height) {
            return $this->length * $this->width * $this->height;
        }
        return null;
    }

    /**
     * Accessor: Get profit margin (selling - purchase price)
     * 
     * @return float|null
     */
    public function getProfitMarginAttribute()
    {
        if ($this->selling_price && $this->purchase_price) {
            return $this->selling_price - $this->purchase_price;
        }
        return null;
    }

    /**
     * Accessor: Get profit percentage
     * 
     * @return float|null
     */
    public function getProfitPercentageAttribute()
    {
        if ($this->selling_price && $this->purchase_price && $this->purchase_price > 0) {
            return round((($this->selling_price - $this->purchase_price) / $this->purchase_price) * 100, 2);
        }
        return null;
    }

    /**
     * Helper method: Get total stock di semua warehouse
     * 
     * @return float
     */
    public function getTotalStock()
    {
        return $this->itemStocks()->sum('current_stock');
    }

    /**
     * Helper method: Get stock di warehouse tertentu
     * 
     * @param int $warehouseId
     * @return float
     */
    public function getStockInWarehouse($warehouseId)
    {
        $stock = $this->itemStocks()->where('warehouse_id', $warehouseId)->first();
        return $stock ? $stock->current_stock : 0;
    }

    /**
     * Helper method: Get available stock di warehouse tertentu
     * 
     * @param int $warehouseId
     * @return float
     */
    public function getAvailableStockInWarehouse($warehouseId)
    {
        $stock = $this->itemStocks()->where('warehouse_id', $warehouseId)->first();
        return $stock ? $stock->available_stock : 0;
    }

    /**
     * Helper method: Check apakah item perlu reorder di warehouse tertentu
     * 
     * @param int $warehouseId
     * @return bool
     */
    public function needsReorderInWarehouse($warehouseId)
    {
        $currentStock = $this->getStockInWarehouse($warehouseId);
        return $this->reorder_point && $currentStock <= $this->reorder_point;
    }

    /**
     * Helper method: Get stock status summary
     * 
     * @return array
     */
    public function getStockSummary()
    {
        $stocks = $this->itemStocks()->with('warehouse')->get();
        
        return [
            'total_stock' => $stocks->sum('current_stock'),
            'total_available' => $stocks->sum('available_stock'),
            'total_reserved' => $stocks->sum('reserved_stock'),
            'warehouses_count' => $stocks->count(),
            'low_stock_warehouses' => $stocks->where('stock_status', 'low')->count(),
            'out_of_stock_warehouses' => $stocks->where('stock_status', 'out_of_stock')->count(),
            'warehouses' => $stocks->map(function ($stock) {
                return [
                    'warehouse_id' => $stock->warehouse_id,
                    'warehouse_name' => $stock->warehouse->name,
                    'current_stock' => $stock->current_stock,
                    'available_stock' => $stock->available_stock,
                    'stock_status' => $stock->stock_status,
                ];
            })
        ];
    }

    /**
     * Helper method: Get movement history terbaru
     * 
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRecentMovements($limit = 10)
    {
        return $this->stockMovements()
                    ->with(['warehouse', 'user'])
                    ->orderBy('movement_date', 'desc')
                    ->limit($limit)
                    ->get();
    }

    /**
     * Helper method: Get transaction history
     * 
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTransactionHistory($limit = 10)
    {
        return $this->transactionDetails()
                    ->with(['transaction.warehouse', 'transaction.vendor'])
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
    }

    /**
     * Helper method: Calculate average cost berdasarkan transaction history
     * 
     * @return float
     */
    public function calculateAverageCost()
    {
        $purchases = $this->transactionDetails()
                          ->whereHas('transaction', function ($query) {
                              $query->where('type', 'IN')->where('status', 'executed');
                          })
                          ->whereNotNull('unit_price')
                          ->get();

        if ($purchases->isEmpty()) {
            return $this->purchase_price ?? 0;
        }

        $totalQuantity = $purchases->sum('quantity');
        $totalValue = $purchases->sum(function ($detail) {
            return $detail->quantity * $detail->unit_price;
        });

        return $totalQuantity > 0 ? round($totalValue / $totalQuantity, 2) : 0;
    }

    /**
     * Helper method: Check apakah item aktif
     * 
     * @return bool
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * Helper method: Check apakah item discontinued
     * 
     * @return bool
     */
    public function isDiscontinued()
    {
        return $this->status === 'discontinued';
    }

    /**
     * Helper method: Generate QR code untuk item
     * 
     * @return string
     */
    public function generateQRCode()
    {
        // Data yang akan di-encode dalam QR code
        $qrData = [
            'type' => 'item',
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'url' => url("/api/items/{$this->id}")
        ];

        return base64_encode(json_encode($qrData));
    }

    /**
     * Boot method untuk handle events
     */
    protected static function boot()
    {
        parent::boot();

        // Event: Sebelum create item baru
        static::creating(function ($item) {
            // Auto generate SKU jika tidak diisi
            if (empty($item->sku)) {
                $category = Category::find($item->category_id);
                $categoryCode = $category ? $category->code : 'ITM';
                $count = Item::where('category_id', $item->category_id)->count() + 1;
                $item->sku = $categoryCode . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }

            // Set default currency jika tidak diisi
            if (empty($item->currency)) {
                $item->currency = 'IDR';
            }
        });

        // Event: Setelah create item
        static::created(function ($item) {
            // Create initial stock records untuk semua warehouse (opsional)
            // Bisa di-comment jika tidak diperlukan
            /*
            $warehouses = Warehouse::active()->get();
            foreach ($warehouses as $warehouse) {
                ItemStock::create([
                    'item_id' => $item->id,
                    'warehouse_id' => $warehouse->id,
                    'current_stock' => 0,
                    'available_stock' => 0,
                    'reserved_stock' => 0,
                    'unit' => $item->unit,
                    'minimum_stock' => $item->minimum_stock,
                    'maximum_stock' => $item->maximum_stock,
                    'reorder_point' => $item->reorder_point,
                    'reorder_quantity' => $item->reorder_quantity,
                ]);
            }
            */
        });

        // Event: Sebelum delete item
        static::deleting(function ($item) {
            // Check apakah item masih punya stock
            if ($item->getTotalStock() > 0) {
                throw new \Exception('Cannot delete item with existing stock');
            }

            // Check apakah item masih ada di transaksi aktif
            if ($item->transactionDetails()->whereHas('transaction', function ($query) {
                $query->whereIn('status', ['draft', 'pending', 'approved']);
            })->count() > 0) {
                throw new \Exception('Cannot delete item with active transactions');
            }
        });
    }
}