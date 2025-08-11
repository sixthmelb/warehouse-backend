<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model Vendor untuk warehouse management system
 * 
 * Relationships:
 * - HasMany: transactions (transaksi pembelian dari vendor ini)
 * 
 * File: app/Models/Vendor.php
 */
class Vendor extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nama tabel di database
     */
    protected $table = 'vendors';

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
        'phone',
        'fax',
        'email',
        'website',
        'tax_number',
        'business_license',
        'vendor_type',
        'contact_person_name',
        'contact_person_phone',
        'contact_person_email',
        'contact_person_position',
        'payment_terms_days',
        'payment_method',
        'credit_limit',
        'status',
        'rating',
        'notes',
    ];

    /**
     * Field yang di-cast ke tipe data tertentu
     */
    protected $casts = [
        'payment_terms_days' => 'integer',
        'credit_limit' => 'decimal:2',
        'rating' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Enum values untuk vendor_type
     */
    const VENDOR_TYPES = [
        'supplier' => 'Supplier',
        'distributor' => 'Distributor',
        'manufacturer' => 'Manufacturer',
        'other' => 'Other'
    ];

    /**
     * Enum values untuk payment_method
     */
    const PAYMENT_METHODS = [
        'cash' => 'Cash',
        'transfer' => 'Bank Transfer',
        'check' => 'Check',
        'credit' => 'Credit'
    ];

    /**
     * Enum values untuk status
     */
    const STATUSES = [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'blacklist' => 'Blacklisted'
    ];

    /**
     * Relationship: Vendor memiliki banyak transaksi pembelian
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Relationship: Vendor transactions yang merupakan pembelian (IN)
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function purchaseTransactions()
    {
        return $this->hasMany(Transaction::class)->where('type', 'IN');
    }

    /**
     * Relationship: Vendor transactions yang merupakan return (OUT)
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function returnTransactions()
    {
        return $this->hasMany(Transaction::class)->where('type', 'OUT');
    }

    /**
     * Scope: Filter vendor yang aktif
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Filter vendor berdasarkan tipe
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, $type)
    {
        return $query->where('vendor_type', $type);
    }

    /**
     * Scope: Filter vendor berdasarkan kota
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
     * Scope: Filter vendor dengan rating minimal
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param float $minRating
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithMinRating($query, $minRating)
    {
        return $query->where('rating', '>=', $minRating);
    }

    /**
     * Scope: Filter vendor yang tidak di blacklist
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotBlacklisted($query)
    {
        return $query->where('status', '!=', 'blacklist');
    }

    /**
     * Accessor: Get vendor type dalam bentuk label
     * 
     * @return string
     */
    public function getVendorTypeLabelAttribute()
    {
        return self::VENDOR_TYPES[$this->vendor_type] ?? $this->vendor_type;
    }

    /**
     * Accessor: Get payment method dalam bentuk label
     * 
     * @return string
     */
    public function getPaymentMethodLabelAttribute()
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;
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
     * Accessor: Get contact person lengkap
     * 
     * @return string
     */
    public function getContactPersonFullAttribute()
    {
        $contact = $this->contact_person_name;
        if ($this->contact_person_position) {
            $contact .= ' (' . $this->contact_person_position . ')';
        }
        return $contact;
    }

    /**
     * Accessor: Get rating dalam bentuk bintang (untuk UI)
     * 
     * @return string
     */
    public function getRatingStarsAttribute()
    {
        if (!$this->rating) return '';
        
        $fullStars = floor($this->rating);
        $halfStar = ($this->rating - $fullStars) >= 0.5 ? 1 : 0;
        $emptyStars = 5 - $fullStars - $halfStar;
        
        return str_repeat('★', $fullStars) . 
               str_repeat('☆', $halfStar) . 
               str_repeat('☆', $emptyStars);
    }

    /**
     * Helper method: Check apakah vendor aktif
     * 
     * @return bool
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * Helper method: Check apakah vendor di blacklist
     * 
     * @return bool
     */
    public function isBlacklisted()
    {
        return $this->status === 'blacklist';
    }

    /**
     * Helper method: Get total pembelian dari vendor (amount)
     * 
     * @return float
     */
    public function getTotalPurchaseAmount()
    {
        return $this->purchaseTransactions()
                    ->where('status', 'executed')
                    ->sum('total_amount');
    }

    /**
     * Helper method: Get jumlah transaksi dengan vendor
     * 
     * @return int
     */
    public function getTotalTransactions()
    {
        return $this->transactions()->count();
    }

    /**
     * Helper method: Get performance summary vendor
     * 
     * @return array
     */
    public function getPerformanceSummary()
    {
        $totalTransactions = $this->transactions()->count();
        $completedTransactions = $this->transactions()->where('status', 'executed')->count();
        $totalAmount = $this->getTotalPurchaseAmount();
        
        return [
            'total_transactions' => $totalTransactions,
            'completed_transactions' => $completedTransactions,
            'completion_rate' => $totalTransactions > 0 ? round(($completedTransactions / $totalTransactions) * 100, 2) : 0,
            'total_purchase_amount' => $totalAmount,
            'average_transaction_amount' => $completedTransactions > 0 ? round($totalAmount / $completedTransactions, 2) : 0,
            'current_rating' => $this->rating,
            'payment_terms' => $this->payment_terms_days . ' days',
        ];
    }

    /**
     * Helper method: Get recent transactions dengan vendor
     * 
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRecentTransactions($limit = 10)
    {
        return $this->transactions()
                    ->with(['warehouse'])
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
    }

    /**
     * Helper method: Check apakah vendor reliable (rating >= 4.0)
     * 
     * @return bool
     */
    public function isReliable()
    {
        return $this->rating && $this->rating >= 4.0;
    }

    /**
     * Helper method: Update rating vendor berdasarkan performance
     * 
     * @return void
     */
    public function updateRating()
    {
        $performance = $this->getPerformanceSummary();
        
        // Simple rating calculation based on completion rate
        // Bisa dikembangkan dengan algorithm yang lebih kompleks
        $completionRate = $performance['completion_rate'];
        
        if ($completionRate >= 95) {
            $newRating = 5.0;
        } elseif ($completionRate >= 90) {
            $newRating = 4.5;
        } elseif ($completionRate >= 80) {
            $newRating = 4.0;
        } elseif ($completionRate >= 70) {
            $newRating = 3.5;
        } elseif ($completionRate >= 60) {
            $newRating = 3.0;
        } else {
            $newRating = 2.5;
        }
        
        $this->update(['rating' => $newRating]);
    }

    /**
     * Boot method untuk handle events
     */
    protected static function boot()
    {
        parent::boot();

        // Event: Sebelum create vendor baru
        static::creating(function ($vendor) {
            // Auto generate code jika tidak diisi
            if (empty($vendor->code)) {
                $vendor->code = 'VND' . str_pad(Vendor::count() + 1, 3, '0', STR_PAD_LEFT);
            }
        });

        // Event: Sebelum delete vendor
        static::deleting(function ($vendor) {
            // Check apakah vendor masih punya transaksi aktif
            if ($vendor->transactions()->whereIn('status', ['draft', 'pending', 'approved'])->count() > 0) {
                throw new \Exception('Cannot delete vendor with active transactions');
            }
        });
    }
}