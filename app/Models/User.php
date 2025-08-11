<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Model User untuk warehouse management system
 * 
 * Relationships:
 * - HasMany: transactions (sebagai creator)
 * - HasMany: transactions (sebagai approver) 
 * - HasMany: stockMovements (sebagai executor)
 * - BelongsToMany: warehouses (user bisa akses multiple warehouse)
 * 
 * File: app/Models/User.php
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Nama tabel di database
     */
    protected $table = 'users';

    /**
     * Field yang bisa di-mass assignment
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_id',
        'phone',
        'role',
        'status',
        'warehouse_access',
    ];

    /**
     * Field yang disembunyikan saat serialization (JSON response)
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Field yang di-cast ke tipe data tertentu
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', // Laravel 10 feature untuk auto hash password
        'warehouse_access' => 'array', // JSON field di-cast ke array
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Enum values untuk role
     */
    const ROLES = [
        'admin' => 'Administrator',
        'manager' => 'Manager', 
        'staff' => 'Staff'
    ];

    /**
     * Enum values untuk status
     */
    const STATUSES = [
        'active' => 'Active',
        'inactive' => 'Inactive'
    ];

    /**
     * Relationship: User bisa membuat banyak transaksi
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function createdTransactions()
    {
        return $this->hasMany(Transaction::class, 'created_by');
    }

    /**
     * Relationship: User bisa menyetujui banyak transaksi
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function approvedTransactions()
    {
        return $this->hasMany(Transaction::class, 'approved_by');
    }

    /**
     * Relationship: User bisa melakukan banyak stock movement
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'user_id');
    }

    /**
     * Relationship: User bisa menyetujui banyak stock movement
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function approvedStockMovements()
    {
        return $this->hasMany(StockMovement::class, 'approved_by');
    }

    /**
     * Relationship: User bisa mengakses banyak warehouse (many-to-many)
     * Menggunakan JSON field warehouse_access untuk simplicity
     * Atau bisa dibuat pivot table jika butuh data tambahan
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function warehouses()
    {
        // Untuk sementara kita gunakan method helper untuk get warehouses
        // Bisa di-refactor ke pivot table jika butuh relasi yang lebih kompleks
        return $this->belongsToMany(Warehouse::class, 'user_warehouse_access')
                    ->withTimestamps();
    }

    /**
     * Scope: Filter user berdasarkan role
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $role
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope: Filter user yang aktif
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Filter user yang bisa akses warehouse tertentu
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $warehouseId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHasWarehouseAccess($query, $warehouseId)
    {
        return $query->whereJsonContains('warehouse_access', $warehouseId);
    }

    /**
     * Accessor: Get user role dalam bentuk label
     * 
     * @return string
     */
    public function getRoleLabelAttribute()
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    /**
     * Accessor: Get user status dalam bentuk label
     * 
     * @return string
     */
    public function getStatusLabelAttribute()
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Helper method: Check apakah user adalah admin
     * 
     * @return bool
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * Helper method: Check apakah user adalah manager
     * 
     * @return bool
     */
    public function isManager()
    {
        return $this->role === 'manager';
    }

    /**
     * Helper method: Check apakah user adalah staff
     * 
     * @return bool
     */
    public function isStaff()
    {
        return $this->role === 'staff';
    }

    /**
     * Helper method: Check apakah user bisa akses warehouse tertentu
     * 
     * @param int $warehouseId
     * @return bool
     */
    public function canAccessWarehouse($warehouseId)
    {
        if ($this->isAdmin()) {
            return true; // Admin bisa akses semua warehouse
        }

        $warehouseAccess = $this->warehouse_access ?? [];
        return in_array($warehouseId, $warehouseAccess);
    }

    /**
     * Helper method: Get list warehouse yang bisa diakses user
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAccessibleWarehouses()
    {
        if ($this->isAdmin()) {
            return Warehouse::active()->get();
        }

        $warehouseIds = $this->warehouse_access ?? [];
        return Warehouse::whereIn('id', $warehouseIds)->active()->get();
    }

    /**
     * Boot method untuk handle events
     */
    protected static function boot()
    {
        parent::boot();

        // Event: Sebelum create user baru
        static::creating(function ($user) {
            // Auto generate employee_id jika tidak diisi
            if (empty($user->employee_id)) {
                $user->employee_id = 'EMP' . str_pad(User::count() + 1, 4, '0', STR_PAD_LEFT);
            }
        });

        // Event: Sebelum delete user
        static::deleting(function ($user) {
            // Check apakah user masih punya transaksi aktif
            if ($user->createdTransactions()->whereIn('status', ['draft', 'pending', 'approved'])->count() > 0) {
                throw new \Exception('Cannot delete user with active transactions');
            }
        });
    }
    /**
     * Token abilities untuk role-based access
     */
    const TOKEN_ABILITIES = [
        'admin' => [
            'warehouses:create', 'warehouses:read', 'warehouses:update', 'warehouses:delete',
            'vendors:create', 'vendors:read', 'vendors:update', 'vendors:delete',
            'categories:create', 'categories:read', 'categories:update', 'categories:delete',
            'items:create', 'items:read', 'items:update', 'items:delete',
            'transactions:create', 'transactions:read', 'transactions:update', 'transactions:delete',
            'transactions:approve', 'transactions:execute',
            'users:create', 'users:read', 'users:update', 'users:delete',
            'reports:read', 'stock:manage',
        ],
        'manager' => [
            'warehouses:read',
            'vendors:create', 'vendors:read', 'vendors:update',
            'categories:create', 'categories:read', 'categories:update',
            'items:create', 'items:read', 'items:update',
            'transactions:create', 'transactions:read', 'transactions:update',
            'transactions:approve', 'transactions:execute',
            'users:read',
            'reports:read', 'stock:manage',
        ],
        'staff' => [
            'warehouses:read',
            'vendors:read',
            'categories:read',
            'items:read',
            'transactions:create', 'transactions:read', 'transactions:update',
            'stock:read',
        ],
    ];

    /**
     * Get token abilities berdasarkan user role
     */
    public function getTokenAbilities()
    {
        return self::TOKEN_ABILITIES[$this->role] ?? self::TOKEN_ABILITIES['staff'];
    }

    /**
     * Create API token dengan abilities sesuai role
     */
    public function createApiToken($tokenName = 'API Token')
    {
        return $this->createToken($tokenName, $this->getTokenAbilities());
    }
}