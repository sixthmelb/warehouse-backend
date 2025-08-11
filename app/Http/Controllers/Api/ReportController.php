<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\ItemStock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * ReportController untuk reporting dan analytics
 * Generate dashboard data dan various reports
 * 
 * Endpoints:
 * - GET /api/v1/reports/dashboard (dashboard summary)
 * - GET /api/v1/reports/stock-summary (stock reports)
 * - GET /api/v1/reports/transaction-summary (transaction reports)
 * - GET /api/v1/reports/vendor-performance (vendor reports)
 * - GET /api/v1/reports/inventory-valuation (inventory valuation)
 * 
 * File: app/Http/Controllers/Api/ReportController.php
 */
class ReportController extends Controller
{
    /**
     * Get dashboard summary data
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $warehouseId = $request->get('warehouse_id');
            $dateRange = $request->get('date_range', 'today'); // today, week, month

            // Filter berdasarkan warehouse access untuk non-admin
            $user = $request->user();
            $accessibleWarehouses = [];
            if (!$user->isAdmin()) {
                $accessibleWarehouses = $user->warehouse_access ?? [];
            }

            // Transaction Statistics
            $transactionStats = Transaction::getDashboardStats($warehouseId, $dateRange);

            // Stock Alerts
            $stockAlerts = ItemStock::getStockAlerts($warehouseId);

            // Stock Valuation
            $stockValuation = ItemStock::getStockValuation($warehouseId);

            // Recent Activities (last 10 transactions)
            $recentTransactionsQuery = Transaction::with(['warehouse', 'vendor', 'createdBy'])
                                                 ->orderBy('created_at', 'desc')
                                                 ->limit(10);

            if ($warehouseId) {
                $recentTransactionsQuery->where('warehouse_id', $warehouseId);
            } elseif (!empty($accessibleWarehouses)) {
                $recentTransactionsQuery->whereIn('warehouse_id', $accessibleWarehouses);
            }

            $recentTransactions = $recentTransactionsQuery->get()->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'transaction_number' => $transaction->transaction_number,
                    'type_label' => $transaction->type_label,
                    'warehouse_name' => $transaction->warehouse->name,
                    'status_label' => $transaction->status_label,
                    'total_amount' => $transaction->total_amount,
                    'created_by' => $transaction->createdBy->name,
                    'created_at' => $transaction->created_at,
                ];
            });

            // Top Items by Value
            $topItemsQuery = ItemStock::with(['item'])
                                    ->where('current_stock', '>', 0)
                                    ->orderBy('total_value', 'desc')
                                    ->limit(5);

            if ($warehouseId) {
                $topItemsQuery->where('warehouse_id', $warehouseId);
            } elseif (!empty($accessibleWarehouses)) {
                $topItemsQuery->whereIn('warehouse_id', $accessibleWarehouses);
            }

            $topItems = $topItemsQuery->get()->map(function ($stock) {
                return [
                    'item_name' => $stock->item->name,
                    'item_sku' => $stock->item->sku,
                    'current_stock' => $stock->current_stock,
                    'total_value' => $stock->total_value,
                    'average_cost' => $stock->average_cost,
                ];
            });

            // Monthly Transaction Trend (last 6 months)
            $monthlyTrend = Transaction::select(
                                DB::raw('YEAR(transaction_date) as year'),
                                DB::raw('MONTH(transaction_date) as month'),
                                DB::raw('COUNT(*) as total_transactions'),
                                DB::raw('SUM(CASE WHEN type = "IN" THEN 1 ELSE 0 END) as incoming'),
                                DB::raw('SUM(CASE WHEN type = "OUT" THEN 1 ELSE 0 END) as outgoing'),
                                DB::raw('SUM(total_amount) as total_value')
                            )
                            ->where('transaction_date', '>=', Carbon::now()->subMonths(6))
                            ->when($warehouseId, function ($q) use ($warehouseId) {
                                return $q->where('warehouse_id', $warehouseId);
                            })
                            ->when(!empty($accessibleWarehouses), function ($q) use ($accessibleWarehouses) {
                                return $q->whereIn('warehouse_id', $accessibleWarehouses);
                            })
                            ->groupBy('year', 'month')
                            ->orderBy('year')
                            ->orderBy('month')
                            ->get()
                            ->map(function ($item) {
                                return [
                                    'period' => Carbon::create($item->year, $item->month)->format('M Y'),
                                    'total_transactions' => $item->total_transactions,
                                    'incoming' => $item->incoming,
                                    'outgoing' => $item->outgoing,
                                    'total_value' => $item->total_value,
                                ];
                            });

            // Warehouse Summary (if user has access to multiple warehouses)
            $warehouseSummary = [];
            if (!$warehouseId && $user->isAdmin()) {
                $warehouseSummary = Warehouse::with(['itemStocks'])
                                           ->active()
                                           ->get()
                                           ->map(function ($warehouse) {
                                               $summary = $warehouse->getStockSummary();
                                               return [
                                                   'id' => $warehouse->id,
                                                   'name' => $warehouse->name,
                                                   'code' => $warehouse->code,
                                                   'total_items' => $summary['total_items'],
                                                   'total_stock_value' => $summary['total_stock_value'],
                                                   'low_stock_items' => $summary['low_stock_items'],
                                                   'out_of_stock_items' => $summary['out_of_stock_items'],
                                               ];
                                           });
            }

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data retrieved successfully',
                'data' => [
                    'transaction_stats' => $transactionStats,
                    'stock_alerts' => $stockAlerts,
                    'stock_valuation' => $stockValuation,
                    'recent_transactions' => $recentTransactions,
                    'top_items_by_value' => $topItems,
                    'monthly_trend' => $monthlyTrend,
                    'warehouse_summary' => $warehouseSummary,
                ],
                'filters' => [
                    'warehouse_id' => $warehouseId,
                    'date_range' => $dateRange,
                    'generated_at' => now(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock summary reports
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function stockSummary(Request $request): JsonResponse
    {
        try {
            $warehouseId = $request->get('warehouse_id');
            $categoryId = $request->get('category_id');
            $reportType = $request->get('type', 'summary'); // summary, detailed, alerts

            $query = ItemStock::with(['item.category', 'warehouse']);

            // Apply filters
            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }

            if ($categoryId) {
                $query->whereHas('item', function ($q) use ($categoryId) {
                    $q->where('category_id', $categoryId);
                });
            }

            switch ($reportType) {
                case 'alerts':
                    $data = [
                        'low_stock' => $query->clone()->lowStock()->get(),
                        'out_of_stock' => $query->clone()->outOfStock()->get(),
                        'reorder_needed' => $query->clone()->needsReorder()->get(),
                        'expiry_alerts' => $query->clone()->expiryAlert()->get(),
                    ];
                    break;

                case 'detailed':
                    $stocks = $query->get();
                    $data = $stocks->map(function ($stock) {
                        return $stock->getStockSummary();
                    });
                    break;

                default: // summary
                    $summary = [
                        'total_items' => $query->count(),
                        'total_stock_value' => $query->sum('total_value'),
                        'items_with_stock' => $query->hasStock()->count(),
                        'items_without_stock' => $query->where('current_stock', 0)->count(),
                        'low_stock_items' => $query->lowStock()->count(),
                        'reorder_alerts' => $query->needsReorder()->count(),
                        'expiry_alerts' => $query->expiryAlert()->count(),
                    ];

                    // Group by category
                    $byCategory = $query->get()
                                       ->groupBy('item.category.name')
                                       ->map(function ($stocks, $categoryName) {
                                           return [
                                               'category_name' => $categoryName,
                                               'total_items' => $stocks->count(),
                                               'total_value' => $stocks->sum('total_value'),
                                               'total_quantity' => $stocks->sum('current_stock'),
                                           ];
                                       })
                                       ->values();

                    // Group by warehouse
                    $byWarehouse = $query->get()
                                        ->groupBy('warehouse.name')
                                        ->map(function ($stocks, $warehouseName) {
                                            return [
                                                'warehouse_name' => $warehouseName,
                                                'total_items' => $stocks->count(),
                                                'total_value' => $stocks->sum('total_value'),
                                                'total_quantity' => $stocks->sum('current_stock'),
                                                'low_stock_items' => $stocks->where('stock_status', 'low')->count(),
                                                'out_of_stock_items' => $stocks->where('stock_status', 'out_of_stock')->count(),
                                            ];
                                        })
                                        ->values();

                    $data = [
                        'summary' => $summary,
                        'by_category' => $byCategory,
                        'by_warehouse' => $byWarehouse,
                    ];
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Stock summary report generated successfully',
                'data' => $data,
                'filters' => [
                    'warehouse_id' => $warehouseId,
                    'category_id' => $categoryId,
                    'report_type' => $reportType,
                    'generated_at' => now(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate stock summary report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transaction summary reports
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function transactionSummary(Request $request): JsonResponse
    {
        try {
            $warehouseId = $request->get('warehouse_id');
            $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->toDateString());
            $dateTo = $request->get('date_to', Carbon::now()->endOfMonth()->toDateString());
            $reportType = $request->get('type', 'summary'); // summary, detailed, daily

            $query = Transaction::with(['warehouse', 'vendor', 'createdBy']);

            // Apply filters
            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }

            $query->whereBetween('transaction_date', [$dateFrom, $dateTo]);

            switch ($reportType) {
                case 'daily':
                    $dailyStats = $query->select(
                                        DB::raw('DATE(transaction_date) as date'),
                                        DB::raw('COUNT(*) as total_transactions'),
                                        DB::raw('SUM(CASE WHEN type = "IN" THEN 1 ELSE 0 END) as incoming'),
                                        DB::raw('SUM(CASE WHEN type = "OUT" THEN 1 ELSE 0 END) as outgoing'),
                                        DB::raw('SUM(total_amount) as total_value'),
                                        DB::raw('AVG(total_amount) as avg_value')
                                    )
                                    ->groupBy('date')
                                    ->orderBy('date')
                                    ->get();

                    $data = $dailyStats->map(function ($stat) {
                        return [
                            'date' => $stat->date,
                            'total_transactions' => $stat->total_transactions,
                            'incoming' => $stat->incoming,
                            'outgoing' => $stat->outgoing,
                            'total_value' => $stat->total_value,
                            'average_value' => $stat->avg_value,
                        ];
                    });
                    break;

                case 'detailed':
                    $transactions = $query->get();
                    $data = $transactions->map(function ($transaction) {
                        return [
                            'transaction_number' => $transaction->transaction_number,
                            'type_label' => $transaction->type_label,
                            'sub_type_label' => $transaction->sub_type_label,
                            'warehouse_name' => $transaction->warehouse->name,
                            'vendor_name' => $transaction->vendor ? $transaction->vendor->name : null,
                            'transaction_date' => $transaction->transaction_date,
                            'status_label' => $transaction->status_label,
                            'total_amount' => $transaction->total_amount,
                            'total_items' => $transaction->total_items,
                            'created_by' => $transaction->createdBy->name,
                        ];
                    });
                    break;

                default: // summary
                    $summary = [
                        'total_transactions' => $query->count(),
                        'incoming_transactions' => $query->clone()->where('type', 'IN')->count(),
                        'outgoing_transactions' => $query->clone()->where('type', 'OUT')->count(),
                        'total_value' => $query->sum('total_amount'),
                        'average_value' => $query->avg('total_amount'),
                        'executed_transactions' => $query->clone()->where('status', 'executed')->count(),
                        'pending_transactions' => $query->clone()->whereIn('status', ['draft', 'pending', 'approved'])->count(),
                    ];

                    // By transaction type
                    $byType = $query->select('type', 'sub_type')
                                   ->selectRaw('COUNT(*) as count')
                                   ->selectRaw('SUM(total_amount) as total_value')
                                   ->groupBy('type', 'sub_type')
                                   ->get()
                                   ->map(function ($item) {
                                       return [
                                           'type' => $item->type,
                                           'sub_type' => $item->sub_type,
                                           'count' => $item->count,
                                           'total_value' => $item->total_value,
                                       ];
                                   });

                    // By status
                    $byStatus = $query->select('status')
                                     ->selectRaw('COUNT(*) as count')
                                     ->selectRaw('SUM(total_amount) as total_value')
                                     ->groupBy('status')
                                     ->get()
                                     ->map(function ($item) {
                                         return [
                                             'status' => $item->status,
                                             'count' => $item->count,
                                             'total_value' => $item->total_value,
                                         ];
                                     });

                    // By warehouse
                    $byWarehouse = $query->with('warehouse')
                                        ->select('warehouse_id')
                                        ->selectRaw('COUNT(*) as count')
                                        ->selectRaw('SUM(total_amount) as total_value')
                                        ->groupBy('warehouse_id')
                                        ->get()
                                        ->map(function ($item) {
                                            return [
                                                'warehouse_name' => $item->warehouse->name,
                                                'count' => $item->count,
                                                'total_value' => $item->total_value,
                                            ];
                                        });

                    $data = [
                        'summary' => $summary,
                        'by_type' => $byType,
                        'by_status' => $byStatus,
                        'by_warehouse' => $byWarehouse,
                    ];
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaction summary report generated successfully',
                'data' => $data,
                'filters' => [
                    'warehouse_id' => $warehouseId,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'report_type' => $reportType,
                    'generated_at' => now(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate transaction summary report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get vendor performance reports
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function vendorPerformance(Request $request): JsonResponse
    {
        try {
            $vendorId = $request->get('vendor_id');
            $dateFrom = $request->get('date_from', Carbon::now()->startOfYear()->toDateString());
            $dateTo = $request->get('date_to', Carbon::now()->endOfYear()->toDateString());

            $query = Vendor::with(['transactions' => function ($q) use ($dateFrom, $dateTo) {
                $q->whereBetween('transaction_date', [$dateFrom, $dateTo])
                  ->where('type', 'IN'); // Only purchase transactions
            }]);

            if ($vendorId) {
                $query->where('id', $vendorId);
            }

            $vendors = $query->get();

            $data = $vendors->map(function ($vendor) {
                $transactions = $vendor->transactions;
                $totalTransactions = $transactions->count();
                $completedTransactions = $transactions->where('status', 'executed')->count();
                $totalAmount = $transactions->where('status', 'executed')->sum('total_amount');

                return [
                    'vendor' => [
                        'id' => $vendor->id,
                        'code' => $vendor->code,
                        'name' => $vendor->name,
                        'vendor_type' => $vendor->vendor_type_label,
                        'rating' => $vendor->rating,
                        'is_reliable' => $vendor->isReliable(),
                    ],
                    'performance' => [
                        'total_transactions' => $totalTransactions,
                        'completed_transactions' => $completedTransactions,
                        'completion_rate' => $totalTransactions > 0 ? round(($completedTransactions / $totalTransactions) * 100, 2) : 0,
                        'total_purchase_amount' => $totalAmount,
                        'average_transaction_amount' => $completedTransactions > 0 ? round($totalAmount / $completedTransactions, 2) : 0,
                        'on_time_delivery_rate' => 95, // Placeholder - could be calculated from actual delivery data
                        'quality_score' => $vendor->rating ? ($vendor->rating * 20) : null, // Convert 5-point to 100-point scale
                    ],
                    'recent_transactions' => $transactions->take(5)->map(function ($transaction) {
                        return [
                            'transaction_number' => $transaction->transaction_number,
                            'transaction_date' => $transaction->transaction_date,
                            'status' => $transaction->status,
                            'total_amount' => $transaction->total_amount,
                        ];
                    }),
                ];
            });

            // Overall vendor statistics
            $summary = [
                'total_vendors' => $vendors->count(),
                'active_vendors' => $vendors->where('status', 'active')->count(),
                'reliable_vendors' => $vendors->filter(function ($vendor) {
                    return $vendor->isReliable();
                })->count(),
                'total_purchase_value' => $vendors->sum(function ($vendor) {
                    return $vendor->transactions->where('status', 'executed')->sum('total_amount');
                }),
                'average_vendor_rating' => $vendors->where('rating', '>', 0)->avg('rating'),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Vendor performance report generated successfully',
                'data' => [
                    'summary' => $summary,
                    'vendor_performance' => $data,
                ],
                'filters' => [
                    'vendor_id' => $vendorId,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'generated_at' => now(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate vendor performance report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory valuation report
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function inventoryValuation(Request $request): JsonResponse
    {
        try {
            $warehouseId = $request->get('warehouse_id');
            $asOfDate = $request->get('as_of_date', Carbon::now()->toDateString());

            // Get current stock valuation
            $stockValuation = ItemStock::getStockValuation($warehouseId);

            // Detailed valuation by item
            $query = ItemStock::with(['item.category', 'warehouse'])
                             ->where('current_stock', '>', 0);

            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }

            $detailedValuation = $query->get()->map(function ($stock) {
                return [
                    'item' => [
                        'sku' => $stock->item->sku,
                        'name' => $stock->item->name,
                        'category' => $stock->item->category->name,
                        'brand' => $stock->item->brand,
                    ],
                    'warehouse' => [
                        'name' => $stock->warehouse->name,
                        'code' => $stock->warehouse->code,
                    ],
                    'stock_info' => [
                        'current_stock' => $stock->current_stock,
                        'unit' => $stock->unit,
                        'average_cost' => $stock->average_cost,
                        'last_cost' => $stock->last_cost,
                        'total_value' => $stock->total_value,
                    ],
                ];
            });

            // Valuation trend (last 12 months)
            $valuationTrend = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                
                // This is a simplified calculation - in real implementation,
                // you'd need historical stock data
                $monthlyValue = $stockValuation['total_stock_value'] * (0.85 + ($i * 0.01)); // Mock trend
                
                $valuationTrend[] = [
                    'period' => $month->format('M Y'),
                    'total_value' => $monthlyValue,
                    'item_count' => $stockValuation['total_items'],
                ];
            }

            // Top categories by value
            $categoryValuation = $detailedValuation->groupBy('item.category')
                                                 ->map(function ($items, $category) {
                                                     return [
                                                         'category' => $category,
                                                         'item_count' => $items->count(),
                                                         'total_quantity' => $items->sum('stock_info.current_stock'),
                                                         'total_value' => $items->sum('stock_info.total_value'),
                                                         'average_cost' => $items->avg('stock_info.average_cost'),
                                                     ];
                                                 })
                                                 ->sortByDesc('total_value')
                                                 ->values();

            return response()->json([
                'success' => true,
                'message' => 'Inventory valuation report generated successfully',
                'data' => [
                    'summary' => $stockValuation,
                    'valuation_trend' => $valuationTrend,
                    'by_category' => $categoryValuation,
                    'detailed_items' => $detailedValuation,
                ],
                'filters' => [
                    'warehouse_id' => $warehouseId,
                    'as_of_date' => $asOfDate,
                    'generated_at' => now(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate inventory valuation report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock movement analysis
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function stockMovementAnalysis(Request $request): JsonResponse
    {
        try {
            $warehouseId = $request->get('warehouse_id');
            $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->toDateString());
            $dateTo = $request->get('date_to', Carbon::now()->endOfMonth()->toDateString());

            $query = StockMovement::with(['item', 'warehouse'])
                                 ->whereBetween('movement_date', [$dateFrom, $dateTo]);

            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }

            $movements = $query->get();

            // Summary statistics
            $summary = [
                'total_movements' => $movements->count(),
                'incoming_movements' => $movements->where('movement_type', 'IN')->count(),
                'outgoing_movements' => $movements->where('movement_type', 'OUT')->count(),
                'total_value_in' => $movements->where('movement_type', 'IN')->sum('total_cost'),
                'total_value_out' => $movements->where('movement_type', 'OUT')->sum('total_cost'),
                'most_active_items' => $movements->groupBy('item_id')->map->count()->sortDesc()->take(5),
            ];

            // Daily movement trend
            $dailyTrend = $movements->groupBy(function ($movement) {
                return Carbon::parse($movement->movement_date)->format('Y-m-d');
            })->map(function ($dayMovements, $date) {
                return [
                    'date' => $date,
                    'total_movements' => $dayMovements->count(),
                    'incoming' => $dayMovements->where('movement_type', 'IN')->count(),
                    'outgoing' => $dayMovements->where('movement_type', 'OUT')->count(),
                    'value_in' => $dayMovements->where('movement_type', 'IN')->sum('total_cost'),
                    'value_out' => $dayMovements->where('movement_type', 'OUT')->sum('total_cost'),
                ];
            })->values();

            // By movement reason
            $byReason = $movements->groupBy('movement_reason')
                                 ->map(function ($reasonMovements, $reason) {
                                     return [
                                         'reason' => $reason,
                                         'count' => $reasonMovements->count(),
                                         'total_quantity' => $reasonMovements->sum('quantity_moved'),
                                         'total_value' => $reasonMovements->sum('total_cost'),
                                     ];
                                 })
                                 ->sortByDesc('count')
                                 ->values();

            return response()->json([
                'success' => true,
                'message' => 'Stock movement analysis generated successfully',
                'data' => [
                    'summary' => $summary,
                    'daily_trend' => $dailyTrend,
                    'by_reason' => $byReason,
                ],
                'filters' => [
                    'warehouse_id' => $warehouseId,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'generated_at' => now(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate stock movement analysis',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}