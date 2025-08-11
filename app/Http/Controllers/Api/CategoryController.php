<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * CategoryController untuk category management system
 * Handle hierarchical category management dengan parent-child relationships
 * 
 * Endpoints:
 * - GET /api/v1/categories (index)
 * - POST /api/v1/categories (store)
 * - GET /api/v1/categories/{id} (show)
 * - PUT /api/v1/categories/{id} (update)
 * - DELETE /api/v1/categories/{id} (destroy)
 * - GET /api/v1/categories/tree (tree structure)
 * - POST /api/v1/categories/{id}/move (move category)
 * 
 * File: app/Http/Controllers/Api/CategoryController.php
 */
class CategoryController extends Controller
{
    /**
     * Get list categories dengan hierarchical structure
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Category::with(['parent', 'children']);

            // Filter berdasarkan parent_id (untuk get children)
            if ($request->has('parent_id')) {
                if ($request->parent_id === 'null' || $request->parent_id === null) {
                    $query->whereNull('parent_id'); // Root categories
                } else {
                    $query->where('parent_id', $request->parent_id);
                }
            }

            // Filter berdasarkan level
            if ($request->has('level')) {
                $query->where('level', $request->level);
            }

            // Filter berdasarkan status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Search berdasarkan nama atau code
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'sort_order');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder)->orderBy('name');

            // Pagination atau get all
            if ($request->boolean('paginate', true)) {
                $perPage = $request->get('per_page', 15);
                $categories = $query->paginate($perPage);
                
                $categories->getCollection()->transform(function ($category) {
                    return $this->formatCategoryData($category);
                });

                return response()->json([
                    'success' => true,
                    'message' => 'Categories retrieved successfully',
                    'data' => $categories->items(),
                    'meta' => [
                        'current_page' => $categories->currentPage(),
                        'per_page' => $categories->perPage(),
                        'total' => $categories->total(),
                        'last_page' => $categories->lastPage(),
                    ]
                ], 200);
            } else {
                $categories = $query->get();
                $data = $categories->map(function ($category) {
                    return $this->formatCategoryData($category);
                });

                return response()->json([
                    'success' => true,
                    'message' => 'Categories retrieved successfully',
                    'data' => $data
                ], 200);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get category tree structure
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function tree(Request $request): JsonResponse
    {
        try {
            $includeInactive = $request->boolean('include_inactive', false);
            
            $query = Category::with('descendants');
            if (!$includeInactive) {
                $query->active();
            }
            
            $tree = $query->roots()->ordered()->get();
            
            $treeData = $tree->map(function ($category) {
                return $category->getTreeStructure();
            });

            return response()->json([
                'success' => true,
                'message' => 'Category tree retrieved successfully',
                'data' => $treeData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve category tree',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create category baru
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'code' => 'nullable|string|max:20|unique:categories',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'parent_id' => 'nullable|exists:categories,id',
                'icon' => 'nullable|string|max:100',
                'color' => 'nullable|string|max:7|regex:/^#[a-fA-F0-9]{6}$/', // Hex color
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
                'is_visible' => 'nullable|boolean',
                'attributes' => 'nullable|array',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validasi parent category
            if ($request->parent_id) {
                $parentCategory = Category::find($request->parent_id);
                if (!$parentCategory) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Parent category not found'
                    ], 404);
                }
                
                // Check depth limit (max 5 levels)
                if ($parentCategory->level >= 5) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Maximum category depth exceeded (5 levels)'
                    ], 400);
                }
            }

            DB::beginTransaction();
            
            try {
                // Create category
                $category = Category::create($request->all());
                
                DB::commit();

                $category->load(['parent', 'children']);

                return response()->json([
                    'success' => true,
                    'message' => 'Category created successfully',
                    'data' => [
                        'category' => $this->formatCategoryData($category)
                    ]
                ], 201);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detail category
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $category = Category::with(['parent', 'children', 'items'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Category retrieved successfully',
                'data' => [
                    'category' => [
                        'id' => $category->id,
                        'code' => $category->code,
                        'name' => $category->name,
                        'description' => $category->description,
                        'level' => $category->level,
                        'path' => $category->path,
                        'full_name' => $category->full_name,
                        'breadcrumb' => $category->breadcrumb,
                        'parent' => $category->parent ? [
                            'id' => $category->parent->id,
                            'name' => $category->parent->name,
                            'code' => $category->parent->code,
                        ] : null,
                        'children' => $category->children->map(function ($child) {
                            return [
                                'id' => $child->id,
                                'name' => $child->name,
                                'code' => $child->code,
                                'level' => $child->level,
                                'total_items' => $child->total_items,
                                'is_active' => $child->is_active,
                            ];
                        }),
                        'icon' => $category->icon,
                        'color' => $category->color,
                        'sort_order' => $category->sort_order,
                        'is_active' => $category->is_active,
                        'is_visible' => $category->is_visible,
                        'is_root' => $category->is_root,
                        'has_children' => $category->has_children,
                        'attributes' => $category->attributes,
                        'notes' => $category->notes,
                        'statistics' => $category->getStatistics(),
                        'items_preview' => $category->items->take(5)->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'sku' => $item->sku,
                                'name' => $item->name,
                                'brand' => $item->brand,
                            ];
                        }),
                        'created_at' => $category->created_at,
                        'updated_at' => $category->updated_at,
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update category
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $category = Category::findOrFail($id);

            // Validasi input
            $validator = Validator::make($request->all(), [
                'code' => 'nullable|string|max:20|unique:categories,code,' . $category->id,
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'parent_id' => 'nullable|exists:categories,id',
                'icon' => 'nullable|string|max:100',
                'color' => 'nullable|string|max:7|regex:/^#[a-fA-F0-9]{6}$/',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
                'is_visible' => 'nullable|boolean',
                'attributes' => 'nullable|array',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validasi parent_id change
            if ($request->has('parent_id') && $request->parent_id != $category->parent_id) {
                if ($request->parent_id) {
                    // Check tidak boleh move ke descendant sendiri
                    if ($category->getAllDescendants()->contains('id', $request->parent_id)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Cannot move category to its own descendant'
                        ], 400);
                    }

                    // Check depth limit
                    $newParent = Category::find($request->parent_id);
                    if ($newParent->level >= 5) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Maximum category depth exceeded (5 levels)'
                        ], 400);
                    }
                }
            }

            DB::beginTransaction();
            
            try {
                // Update category
                $category->update($request->all());
                
                DB::commit();

                $category->load(['parent', 'children']);

                return response()->json([
                    'success' => true,
                    'message' => 'Category updated successfully',
                    'data' => [
                        'category' => $this->formatCategoryData($category)
                    ]
                ], 200);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete category
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $category = Category::findOrFail($id);

            // Check apakah bisa dihapus
            if (!$category->canBeDeleted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category with items or child categories'
                ], 400);
            }

            // Soft delete category
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Move category ke parent baru
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function move(Request $request, $id): JsonResponse
    {
        try {
            $category = Category::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'new_parent_id' => 'nullable|exists:categories,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();
            
            try {
                $category->moveTo($request->new_parent_id);
                
                DB::commit();

                $category->load(['parent', 'children']);

                return response()->json([
                    'success' => true,
                    'message' => 'Category moved successfully',
                    'data' => [
                        'category' => $this->formatCategoryData($category)
                    ]
                ], 200);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to move category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder categories dalam parent yang sama
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function reorder(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'parent_id' => 'nullable|exists:categories,id',
                'category_ids' => 'required|array|min:1',
                'category_ids.*' => 'exists:categories,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();
            
            try {
                Category::reorderSiblings($request->parent_id, $request->category_ids);
                
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Categories reordered successfully'
                ], 200);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get categories untuk dropdown/lookup
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function lookup(Request $request): JsonResponse
    {
        try {
            $flatList = Category::getFlatList();

            return response()->json([
                'success' => true,
                'message' => 'Category lookup data retrieved successfully',
                'data' => $flatList
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve category lookup data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format category data untuk response
     * 
     * @param Category $category
     * @return array
     */
    private function formatCategoryData($category): array
    {
        return [
            'id' => $category->id,
            'code' => $category->code,
            'name' => $category->name,
            'description' => $category->description,
            'parent_id' => $category->parent_id,
            'parent_name' => $category->parent ? $category->parent->name : null,
            'level' => $category->level,
            'path' => $category->path,
            'full_name' => $category->full_name,
            'icon' => $category->icon,
            'color' => $category->color,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
            'is_visible' => $category->is_visible,
            'is_root' => $category->is_root,
            'has_children' => $category->has_children,
            'total_items' => $category->total_items,
            'children_count' => $category->children ? $category->children->count() : 0,
            'attributes' => $category->attributes,
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
        ];
    }
}