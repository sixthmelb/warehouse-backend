<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model Category untuk warehouse management system
 * Support hierarchical categories (parent-child relationship)
 * 
 * Relationships:
 * - BelongsTo: parent (parent category)
 * - HasMany: children (child categories)
 * - HasMany: items (barang dalam kategori ini)
 * 
 * File: app/Models/Category.php
 */
class Category extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nama tabel di database
     */
    protected $table = 'categories';

    /**
     * Field yang bisa di-mass assignment
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'parent_id',
        'level',
        'path',
        'icon',
        'color',
        'sort_order',
        'is_active',
        'is_visible',
        'attributes',
        'notes',
    ];

    /**
     * Field yang di-cast ke tipe data tertentu
     */
    protected $casts = [
        'parent_id' => 'integer',
        'level' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_visible' => 'boolean',
        'attributes' => 'array', // JSON field di-cast ke array
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relationship: Category bisa memiliki parent category
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Relationship: Category bisa memiliki banyak child categories
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Relationship: Category memiliki banyak item
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items()
    {
        return $this->hasMany(Item::class);
    }

    /**
     * Relationship: Get all descendants (recursive children)
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function descendants()
    {
        return $this->hasMany(Category::class, 'parent_id')->with('descendants');
    }

    /**
     * Scope: Filter category yang aktif
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter category yang visible
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    /**
     * Scope: Filter root categories (level 1)
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id')->orWhere('level', 1);
    }

    /**
     * Scope: Filter categories berdasarkan level
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $level
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope: Order categories berdasarkan sort_order
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Accessor: Get breadcrumb path untuk category
     * 
     * @return array
     */
    public function getBreadcrumbAttribute()
    {
        $breadcrumb = [];
        $current = $this;
        
        while ($current) {
            array_unshift($breadcrumb, [
                'id' => $current->id,
                'name' => $current->name,
                'code' => $current->code
            ]);
            $current = $current->parent;
        }
        
        return $breadcrumb;
    }

    /**
     * Accessor: Get full name dengan path
     * 
     * @return string
     */
    public function getFullNameAttribute()
    {
        $breadcrumb = $this->getBreadcrumbAttribute();
        return implode(' > ', array_column($breadcrumb, 'name'));
    }

    /**
     * Accessor: Check apakah category adalah root
     * 
     * @return bool
     */
    public function getIsRootAttribute()
    {
        return $this->level === 1 || is_null($this->parent_id);
    }

    /**
     * Accessor: Check apakah category memiliki children
     * 
     * @return bool
     */
    public function getHasChildrenAttribute()
    {
        return $this->children()->count() > 0;
    }

    /**
     * Accessor: Get total items dalam category dan sub-categories
     * 
     * @return int
     */
    public function getTotalItemsAttribute()
    {
        $directItems = $this->items()->count();
        $childrenItems = $this->children->sum('total_items');
        
        return $directItems + $childrenItems;
    }

    /**
     * Helper method: Get all ancestor categories (parents)
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAncestors()
    {
        $ancestors = collect();
        $current = $this->parent;
        
        while ($current) {
            $ancestors->prepend($current);
            $current = $current->parent;
        }
        
        return $ancestors;
    }

    /**
     * Helper method: Get all descendant categories (recursive children)
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllDescendants()
    {
        $descendants = collect();
        
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getAllDescendants());
        }
        
        return $descendants;
    }

    /**
     * Helper method: Get category tree untuk UI (dengan children)
     * 
     * @return array
     */
    public function getTreeStructure()
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'level' => $this->level,
            'icon' => $this->icon,
            'color' => $this->color,
            'is_active' => $this->is_active,
            'is_visible' => $this->is_visible,
            'total_items' => $this->total_items,
            'children' => $this->children->map(function ($child) {
                return $child->getTreeStructure();
            })
        ];
    }

    /**
     * Helper method: Check apakah category bisa dihapus
     * 
     * @return bool
     */
    public function canBeDeleted()
    {
        // Tidak bisa dihapus jika:
        // 1. Masih punya items
        // 2. Masih punya child categories
        return $this->items()->count() === 0 && $this->children()->count() === 0;
    }

    /**
     * Helper method: Move category ke parent baru
     * 
     * @param int|null $newParentId
     * @return bool
     */
    public function moveTo($newParentId)
    {
        // Validasi: tidak bisa move ke descendant sendiri
        if ($newParentId && $this->getAllDescendants()->contains('id', $newParentId)) {
            throw new \Exception('Cannot move category to its own descendant');
        }

        $oldParentId = $this->parent_id;
        $this->parent_id = $newParentId;
        
        // Update level berdasarkan parent baru
        if ($newParentId) {
            $newParent = Category::find($newParentId);
            $this->level = $newParent->level + 1;
        } else {
            $this->level = 1;
        }
        
        // Update path
        $this->updatePath();
        
        return $this->save();
    }

    /**
     * Helper method: Update path berdasarkan hierarchy
     * 
     * @return void
     */
    protected function updatePath()
    {
        if ($this->parent_id) {
            $parentPath = $this->parent->path ?? $this->parent->id;
            $this->path = $parentPath . '/' . $this->id;
        } else {
            $this->path = (string) $this->id;
        }
        
        // Update path untuk semua children
        foreach ($this->children as $child) {
            $child->updatePath();
            $child->save();
        }
    }

    /**
     * Helper method: Get siblings (categories dengan parent yang sama)
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSiblings()
    {
        return Category::where('parent_id', $this->parent_id)
                      ->where('id', '!=', $this->id)
                      ->ordered()
                      ->get();
    }

    /**
     * Helper method: Reorder categories dalam parent yang sama
     * 
     * @param array $orderedIds
     * @return bool
     */
    public static function reorderSiblings($parentId, array $orderedIds)
    {
        $sortOrder = 1;
        foreach ($orderedIds as $categoryId) {
            Category::where('id', $categoryId)
                   ->where('parent_id', $parentId)
                   ->update(['sort_order' => $sortOrder]);
            $sortOrder++;
        }
        
        return true;
    }

    /**
     * Helper method: Get statistics untuk category
     * 
     * @return array
     */
    public function getStatistics()
    {
        return [
            'direct_items' => $this->items()->count(),
            'total_items' => $this->total_items,
            'direct_children' => $this->children()->count(),
            'total_descendants' => $this->getAllDescendants()->count(),
            'level' => $this->level,
            'is_leaf' => !$this->has_children,
        ];
    }

    /**
     * Static method: Build tree structure untuk semua categories
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getTree()
    {
        return Category::with('children.children.children') // Load 3 levels deep
                      ->roots()
                      ->active()
                      ->ordered()
                      ->get()
                      ->map(function ($category) {
                          return $category->getTreeStructure();
                      });
    }

    /**
     * Static method: Get flat list categories untuk select options
     * 
     * @return array
     */
    public static function getFlatList()
    {
        return Category::with('parent')
                      ->active()
                      ->ordered()
                      ->get()
                      ->map(function ($category) {
                          return [
                              'id' => $category->id,
                              'name' => $category->full_name,
                              'level' => $category->level,
                              'indent' => str_repeat('-- ', $category->level - 1)
                          ];
                      });
    }

    /**
     * Boot method untuk handle events
     */
    protected static function boot()
    {
        parent::boot();

        // Event: Sebelum create category baru
        static::creating(function ($category) {
            // Auto generate code jika tidak diisi
            if (empty($category->code)) {
                $category->code = 'CAT' . str_pad(Category::count() + 1, 3, '0', STR_PAD_LEFT);
            }

            // Set level berdasarkan parent
            if ($category->parent_id) {
                $parent = Category::find($category->parent_id);
                $category->level = $parent->level + 1;
            } else {
                $category->level = 1;
            }

            // Set sort_order otomatis jika tidak diisi
            if (!$category->sort_order) {
                $maxSort = Category::where('parent_id', $category->parent_id)->max('sort_order') ?? 0;
                $category->sort_order = $maxSort + 1;
            }
        });

        // Event: Setelah create category
        static::created(function ($category) {
            // Update path setelah category dibuat (butuh ID)
            $category->updatePath();
            $category->save();
        });

        // Event: Sebelum update category
        static::updating(function ($category) {
            // Jika parent_id berubah, update level dan path
            if ($category->isDirty('parent_id')) {
                if ($category->parent_id) {
                    $parent = Category::find($category->parent_id);
                    $category->level = $parent->level + 1;
                } else {
                    $category->level = 1;
                }
            }
        });

        // Event: Setelah update category
        static::updated(function ($category) {
            // Update path jika parent berubah
            if ($category->wasChanged('parent_id')) {
                $category->updatePath();
            }
        });

        // Event: Sebelum delete category
        static::deleting(function ($category) {
            // Check apakah bisa dihapus
            if (!$category->canBeDeleted()) {
                throw new \Exception('Cannot delete category with items or child categories');
            }
        });
    }
}