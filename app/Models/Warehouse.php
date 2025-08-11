<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model Warehouse untuk warehouse management system
 * 
 * Relationships:
 * - HasMany: transactions (transaksi yang terjadi di warehouse ini)
 * - HasMany: itemStocks (stock barang di warehouse ini)
 * - HasMany: stockMovements (pergerakan stock di warehouse ini)
 * - BelongsToMany: users (user yang bisa akses warehouse ini)
 * 
 * File: app/Models/Warehouse.php
 */
class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nama tabel di database
     */
    protected $table = 'warehouses';

    /**
     * Field yang bisa di-mass assignment
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'address',
        'city',
        'province',
        'postal_code',
        'latitude',
        'longitude',
        'capacity',
        'capacity_unit',
        'status',
        'manager_name',
        'manager_phone',
        'manager_email',
    ];

    /**
     * Field yang di-cast ke tipe data tertentu
     */
    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'capacity' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Enum values untuk status
     */
    const STATUSES = [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'maintenance' => 'Under Maintenance'
    ];

    /**
     * Relationship: Warehouse memiliki banyak transaksi
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Relationship: Warehouse memiliki stock untuk banyak item
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function itemStocks()
    {
        return $this->hasMany(ItemStock::class);
    }

    /**
     * Relationship: Warehouse memiliki banyak stock movement
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Relationship: Warehouse bisa diakses oleh banyak user (many-to-many)
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_warehouse_access')
                    ->withTimestamps();
    }

    /**
     * Scope: Filter warehouse yang aktif
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Filter warehouse berdasarkan kota
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $city
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCity($query, $city)
    {
        return $query->where('city', 'like', "%{$city}%");
    }

    /**
     * Scope: Filter warehouse berdasarkan provinsi
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $province
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByProvince($query, $province)
    {
        return $query->where('province', 'like', "%{$province}%");
    }

    /**
     * Scope: Filter warehouse dalam radius tertentu dari koordinat
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param float $latitude
     * @param float $longitude
     * @param float $radiusKm
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithinRadius($query, $latitude, $longitude, $radiusKm)
    {
        $earthRadius = 6371; // Earth radius in kilometers

        return $query->selectRaw("*, 
            ( {$earthRadius} * acos( cos( radians(?) ) *
            cos( radians( latitude ) ) *
            cos( radians( longitude ) - radians(?) ) +
            sin( radians(?) ) *
            sin( radians( latitude ) ) ) ) AS distance", 
            [$latitude, $longitude, $latitude])
            ->having('distance', '<', $radiusKm)
            ->orderBy('distance');
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
     * Accessor: Get alamat lengkap
     * 
     * @return string
     */
    public function getFullAddressAttribute()
    {
        return trim($this->address . ', ' . $this->city . ', ' . $this->province . ' ' . $this->postal_code);
    }

    /**
     * Accessor: Get total stock value di warehouse ini
     * 
     * @return float
     */
    public function getTotalStockValueAttribute()
    {
        return $this->itemStocks()->sum('total_value');
    }

    /**
     * Accessor: Get total items di warehouse ini
     * 
     * @return int
     */
    public function getTotalItemsAttribute()
    {
        return $this->itemStocks()->count();
    }

    /**
     * Accessor: Get capacity utilization percentage
     * 
     * @return float
     */
    public function getCapacityUtilizationAttribute()
    {
        if (!$this->capacity || $this->capacity <= 0) {
            return 0;
        }

        $totalStock = $this->itemStocks()->sum('current_stock');
        return round(($totalStock / $this->capacity) * 100, 2);
    }

    /**
     * Helper method: Check apakah warehouse sedang maintenance
     * 
     * @return bool
     */
    public function isUnderMaintenance()
    {
        return $this->status === 'maintenance';
    }

    /**
     * Helper method: Check apakah warehouse aktif
     * 
     * @return bool
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * Helper method: Get stock summary untuk dashboard
     * 
     * @return array
     */
    public function getStockSummary()
    {
        $stocks = $this->itemStocks();
        
        return [
            'total_items' => $stocks->count(),
            'total_stock_value' => $stocks->sum('total_value'),
            'low_stock_items' => $stocks->where('stock_status', 'low')->count(),
            'out_of_stock_items' => $stocks->where('stock_status', 'out_of_stock')->count(),
            'expiring_soon' => $stocks->where('expiry_alert', true)->count(),
            'items_requiring_reorder' => $stocks->where('reorder_alert', true)->count(),
        ];
    }

    /**
     * Helper method: Get recent transactions
     * 
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRecentTransactions($limit = 10)
    {
        return $this->transactions()
                    ->with(['vendor', 'createdBy'])
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
    }

    /**
     * Helper method: Get koordinat untuk mapping
     * 
     * @return array|null
     */
    public function getCoordinates()
    {
        if ($this->latitude && $this->longitude) {
            return [
                'lat' => (float) $this->latitude,
                'lng' => (float) $this->longitude
            ];
        }

        return null;
    }

    /**
     * Boot method untuk handle events
     */
    protected static function boot()
    {
        parent::boot();

        // Event: Sebelum create warehouse baru
        static::creating(function ($warehouse) {
            // Auto generate code jika tidak diisi
            if (empty($warehouse->code)) {
                $warehouse->code = 'WH' . str_pad(Warehouse::count() + 1, 3, '0', STR_PAD_LEFT);
            }
        });

        // Event: Sebelum delete warehouse
        static::deleting(function ($warehouse) {
            // Check apakah warehouse masih punya stock
            if ($warehouse->itemStocks()->where('current_stock', '>', 0)->count() > 0) {
                throw new \Exception('Cannot delete warehouse with existing stock');
            }

            // Check apakah warehouse masih punya transaksi aktif
            if ($warehouse->transactions()->whereIn('status', ['draft', 'pending', 'approved'])->count() > 0) {
                throw new \Exception('Cannot delete warehouse with active transactions');
            }
        });
    }
}