<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\TransactionDetailController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\ReportController;

/*
|--------------------------------------------------------------------------
| API Routes - Warehouse Management System (Complete)
|--------------------------------------------------------------------------
*/

// Health check
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Warehouse Management API is running',
        'timestamp' => now(),
        'version' => '1.0.0',
        'environment' => app()->environment(),
    ]);
});

/*
|--------------------------------------------------------------------------
| Public Routes (tidak perlu authentication)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    
    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
    });
    
    // Health check dengan prefix
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'API v1 is running',
            'timestamp' => now(),
            'version' => '1.0.0'
        ]);
    });

    // API Documentation endpoint
    Route::get('/docs', function () {
        return response()->json([
            'success' => true,
            'message' => 'Warehouse Management System API Documentation',
            'version' => '1.0.0',
            'endpoints' => [
                'authentication' => [
                    'POST /api/v1/auth/login' => 'User login',
                    'POST /api/v1/auth/logout' => 'User logout (protected)',
                    'GET /api/v1/auth/user' => 'Get current user (protected)',
                ],
                'warehouses' => [
                    'GET /api/v1/warehouses' => 'List warehouses (protected)',
                    'POST /api/v1/warehouses' => 'Create warehouse (admin/manager)',
                    'GET /api/v1/warehouses/{id}' => 'Get warehouse details (protected)',
                    'PUT /api/v1/warehouses/{id}' => 'Update warehouse (admin/manager)',
                    'DELETE /api/v1/warehouses/{id}' => 'Delete warehouse (admin/manager)',
                ],
                'vendors' => [
                    'GET /api/v1/vendors' => 'List vendors (admin/manager)',
                    'POST /api/v1/vendors' => 'Create vendor (admin/manager)',
                    'GET /api/v1/vendors/{id}' => 'Get vendor details (admin/manager)',
                    'PUT /api/v1/vendors/{id}' => 'Update vendor (admin/manager)',
                ],
                'categories' => [
                    'GET /api/v1/categories' => 'List categories (protected)',
                    'POST /api/v1/categories' => 'Create category (admin/manager)',
                    'GET /api/v1/categories/tree' => 'Get category tree (protected)',
                ],
                'items' => [
                    'GET /api/v1/items' => 'List items (protected)',
                    'POST /api/v1/items' => 'Create item (protected)',
                    'GET /api/v1/items/{id}/stock' => 'Get item stock info (protected)',
                ],
                'transactions' => [
                    'GET /api/v1/transactions' => 'List transactions (protected)',
                    'POST /api/v1/transactions' => 'Create transaction (protected)',
                    'POST /api/v1/transactions/{id}/approve' => 'Approve transaction (admin/manager)',
                    'POST /api/v1/transactions/{id}/execute' => 'Execute transaction (admin/manager)',
                ],
                'stocks' => [
                    'GET /api/v1/stocks' => 'List stock levels (protected)',
                    'GET /api/v1/stocks/alerts' => 'Get stock alerts (protected)',
                    'POST /api/v1/stocks/cycle-count' => 'Perform cycle count (protected)',
                ],
                'reports' => [
                    'GET /api/v1/reports/dashboard' => 'Dashboard data (admin/manager)',
                    'GET /api/v1/reports/stock-summary' => 'Stock reports (admin/manager)',
                    'GET /api/v1/reports/transaction-summary' => 'Transaction reports (admin/manager)',
                ],
            ]
        ]);
    });
});

/*
|--------------------------------------------------------------------------
| Protected Routes (perlu authentication)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | Authentication Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::get('/tokens', [AuthController::class, 'getTokens']);
        Route::post('/revoke-all-tokens', [AuthController::class, 'revokeAllTokens']);
    });

    /*
    |--------------------------------------------------------------------------
    | Category Management (semua authenticated users bisa read)
    |--------------------------------------------------------------------------
    */
    Route::apiResource('categories', CategoryController::class)->except(['create', 'edit']);
    
    // Additional category endpoints
    Route::prefix('categories')->group(function () {
        Route::get('/tree', [CategoryController::class, 'tree']);
        Route::get('/lookup', [CategoryController::class, 'lookup']);
        Route::post('/{category}/move', [CategoryController::class, 'move']);
        Route::post('/reorder', [CategoryController::class, 'reorder']);
    });

    /*
    |--------------------------------------------------------------------------
    | Item Management (semua authenticated users)
    |--------------------------------------------------------------------------
    */
    Route::apiResource('items', ItemController::class)->except(['create', 'edit']);
    
    // Additional item endpoints
    Route::prefix('items')->group(function () {
        Route::get('/{item}/stock', [ItemController::class, 'stock']);
        Route::get('/alerts/reorder', [ItemController::class, 'reorderAlerts']);
        Route::put('/bulk/prices', [ItemController::class, 'bulkUpdatePrices']);
        Route::get('/lookup', [ItemController::class, 'lookup']);
    });

    /*
    |--------------------------------------------------------------------------
    | Transaction Management (semua authenticated users)
    |--------------------------------------------------------------------------
    */
    Route::apiResource('transactions', TransactionController::class)->except(['create', 'edit']);
    
    // Transaction workflow endpoints
    Route::prefix('transactions')->group(function () {
        Route::post('/{transaction}/submit', [TransactionController::class, 'submit']);
        Route::post('/{transaction}/cancel', [TransactionController::class, 'cancel']);
        Route::get('/pending-approval', [TransactionController::class, 'pendingApproval']);
    });

    /*
    |--------------------------------------------------------------------------
    | Stock Management (semua authenticated users)
    |--------------------------------------------------------------------------
    */
    Route::prefix('stocks')->group(function () {
        Route::get('/', [StockController::class, 'index']);
        Route::get('/alerts', [StockController::class, 'alerts']);
        Route::get('/movements', [StockController::class, 'movements']);
        Route::get('/valuation', [StockController::class, 'valuation']);
        Route::get('/report', [StockController::class, 'report']);
        Route::post('/cycle-count', [StockController::class, 'cycleCount']);
        Route::post('/reserve', [StockController::class, 'reserve']);
        Route::post('/release', [StockController::class, 'release']);
    });

    /*
    |--------------------------------------------------------------------------
    | Warehouse Management (Admin & Manager)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:admin,manager'])->group(function () {
        
        // Warehouse CRUD
        Route::apiResource('warehouses', WarehouseController::class)->except(['create', 'edit']);
        
        // Additional warehouse endpoints
        Route::prefix('warehouses')->group(function () {
            Route::get('/{warehouse}/statistics', [WarehouseController::class, 'statistics']);
        });
        
        // Vendor CRUD  
        Route::apiResource('vendors', VendorController::class)->except(['create', 'edit']);
        
        // Additional vendor endpoints
        Route::prefix('vendors')->group(function () {
            Route::get('/{vendor}/performance', [VendorController::class, 'performance']);
            Route::put('/{vendor}/rating', [VendorController::class, 'updateRating']);
        });

        // Transaction approval & execution (Admin & Manager only)
        Route::prefix('transactions')->group(function () {
            Route::post('/{transaction}/approve', [TransactionController::class, 'approve']);
            Route::post('/{transaction}/execute', [TransactionController::class, 'execute']);
        });

        // Category management (create, update, delete - Admin & Manager only)
        // Already handled by role middleware in CategoryController

        // Reports & Analytics
        Route::prefix('reports')->group(function () {
            Route::get('/dashboard', [ReportController::class, 'dashboard']);
            Route::get('/stock-summary', [ReportController::class, 'stockSummary']);
            Route::get('/transaction-summary', [ReportController::class, 'transactionSummary']);
            Route::get('/vendor-performance', [ReportController::class, 'vendorPerformance']);
            Route::get('/inventory-valuation', [ReportController::class, 'inventoryValuation']);
            Route::get('/stock-movement-analysis', [ReportController::class, 'stockMovementAnalysis']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Lookup/Dropdown Data (semua authenticated users)
    |--------------------------------------------------------------------------
    */
    Route::prefix('lookup')->group(function () {
        Route::get('/warehouses', [WarehouseController::class, 'accessible']);
        Route::get('/vendors', [VendorController::class, 'lookup']);
        Route::get('/categories', [CategoryController::class, 'lookup']);
        Route::get('/items', [ItemController::class, 'lookup']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Only Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:admin'])->prefix('admin')->group(function () {
        
        // User Management
        Route::prefix('users')->group(function () {
            Route::get('/', function () {
                return response()->json([
                    'success' => false,
                    'message' => 'UserController not implemented yet',
                    'todo' => 'Create UserController for user management'
                ], 501);
            });
        });

        // System Settings
        Route::prefix('settings')->group(function () {
            Route::get('/', function () {
                return response()->json([
                    'success' => false,
                    'message' => 'SettingsController not implemented yet',
                    'todo' => 'Create SettingsController for system settings'
                ], 501);
            });
        });

        // System Logs
        Route::prefix('logs')->group(function () {
            Route::get('/activities', function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Activity logs not implemented yet',
                    'todo' => 'Create activity logging system'
                ], 501);
            });
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Search & Advanced Features
    |--------------------------------------------------------------------------
    */
    Route::prefix('search')->group(function () {
        Route::get('/global', function (Request $request) {
            $query = $request->get('q');
            if (!$query) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search query is required'
                ], 400);
            }

            // Simple global search implementation
            $results = [
                'items' => \App\Models\Item::search($query)->limit(5)->get(['id', 'sku', 'name']),
                'warehouses' => \App\Models\Warehouse::where('name', 'like', "%{$query}%")->limit(3)->get(['id', 'code', 'name']),
                'vendors' => \App\Models\Vendor::where('name', 'like', "%{$query}%")->limit(3)->get(['id', 'code', 'name']),
                'transactions' => \App\Models\Transaction::where('transaction_number', 'like', "%{$query}%")->limit(5)->get(['id', 'transaction_number', 'type']),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Global search completed',
                'data' => $results,
                'query' => $query
            ]);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Utility Endpoints
    |--------------------------------------------------------------------------
    */
    Route::prefix('utils')->group(function () {
        
        // Generate barcode/QR code
        Route::post('/generate-barcode', function (Request $request) {
            $data = $request->validate([
                'type' => 'required|in:item,transaction,warehouse',
                'id' => 'required|integer',
            ]);

            switch ($data['type']) {
                case 'item':
                    $item = \App\Models\Item::findOrFail($data['id']);
                    $qrCode = $item->generateQRCode();
                    break;
                case 'transaction':
                    $transaction = \App\Models\Transaction::findOrFail($data['id']);
                    $qrCode = base64_encode(json_encode([
                        'type' => 'transaction',
                        'id' => $transaction->id,
                        'number' => $transaction->transaction_number,
                    ]));
                    break;
                default:
                    $qrCode = null;
            }

            return response()->json([
                'success' => true,
                'data' => ['qr_code' => $qrCode]
            ]);
        });

        // System status
        Route::get('/system-status', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'database' => 'connected',
                    'cache' => 'active',
                    'queue' => 'running',
                    'storage' => 'available',
                    'api_version' => '1.0.0',
                    'laravel_version' => app()->version(),
                    'php_version' => phpversion(),
                    'server_time' => now(),
                ]
            ]);
        });
    });
    /*
    |--------------------------------------------------------------------------
    | Transaction Details CRUD (Nested Resource)
    |--------------------------------------------------------------------------
    */
    Route::prefix('transactions/{transaction}/details')->group(function () {
        
        // Standard CRUD Operations
        Route::get('/', [TransactionDetailController::class, 'index'])
            ->name('transaction-details.index');
            
        Route::post('/', [TransactionDetailController::class, 'store'])
            ->name('transaction-details.store');
            
        Route::get('/{detail}', [TransactionDetailController::class, 'show'])
            ->name('transaction-details.show');
            
        Route::put('/{detail}', [TransactionDetailController::class, 'update'])
            ->name('transaction-details.update');
            
        Route::patch('/{detail}', [TransactionDetailController::class, 'update'])
            ->name('transaction-details.patch');
            
        Route::delete('/{detail}', [TransactionDetailController::class, 'destroy'])
            ->name('transaction-details.destroy');

        /*
        |--------------------------------------------------------------------------
        | Bulk Operations
        |--------------------------------------------------------------------------
        */
        Route::post('/bulk', [TransactionDetailController::class, 'bulk'])
            ->name('transaction-details.bulk');

        /*
        |--------------------------------------------------------------------------
        | Utility Operations
        |--------------------------------------------------------------------------
        */
        
        // Reorder line numbers
        Route::post('/reorder', [TransactionDetailController::class, 'reorderDetails'])
            ->name('transaction-details.reorder');

        /*
        |--------------------------------------------------------------------------
        | Individual Detail Operations
        |--------------------------------------------------------------------------
        */
        Route::prefix('{detail}')->group(function () {
            
            // Update actual quantity (untuk execution phase)
            Route::put('/actual-quantity', [TransactionDetailController::class, 'updateActualQuantity'])
                ->name('transaction-details.update-actual-quantity');
                
            // Quality Check
            Route::post('/qc', [TransactionDetailController::class, 'performQualityCheck'])
                ->name('transaction-details.quality-check');
                
            // Duplicate detail
            Route::post('/duplicate', [TransactionDetailController::class, 'duplicate'])
                ->name('transaction-details.duplicate');
                
            // Generate picking label
            Route::get('/picking-label', function (Transaction $transaction, TransactionDetail $detail) {
                if ($detail->transaction_id !== $transaction->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Transaction detail not found in this transaction'
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'picking_label' => $detail->generatePickingLabel(),
                        'qr_code' => $detail->generateQRCode(),
                    ]
                ]);
            })->name('transaction-details.picking-label');
            
            // Get validation status
            Route::get('/validation', function (Transaction $transaction, TransactionDetail $detail) {
                if ($detail->transaction_id !== $transaction->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Transaction detail not found in this transaction'
                    ], 404);
                }

                $errors = [];
                if (!$detail->item) $errors[] = 'Item not found';
                if (!$detail->quantity || $detail->quantity <= 0) $errors[] = 'Invalid quantity';
                if (!$detail->qc_passed) $errors[] = 'Quality check not passed';
                if (in_array($detail->condition, ['damaged', 'expired'])) $errors[] = 'Item condition not acceptable';

                return response()->json([
                    'success' => true,
                    'data' => [
                        'is_valid' => $detail->isValidForExecution(),
                        'validation_errors' => $errors,
                        'detail_id' => $detail->id,
                        'line_number' => $detail->line_number,
                    ]
                ]);
            })->name('transaction-details.validation');
        });

        /*
        |--------------------------------------------------------------------------
        | Batch Operations (Alternative bulk operations)
        |--------------------------------------------------------------------------
        */
        Route::prefix('batch')->group(function () {
            
            // Batch update quantities
            Route::put('/quantities', function (Request $request, Transaction $transaction) {
                $validator = Validator::make($request->all(), [
                    'updates' => 'required|array|min:1',
                    'updates.*.detail_id' => 'required|exists:transaction_details,id',
                    'updates.*.quantity' => 'required|numeric|min:0.01',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'errors' => $validator->errors()
                    ], 422);
                }

                $results = [];
                foreach ($request->updates as $update) {
                    $detail = TransactionDetail::where('id', $update['detail_id'])
                                             ->where('transaction_id', $transaction->id)
                                             ->first();
                    if ($detail) {
                        $detail->update(['quantity' => $update['quantity']]);
                        $results[] = ['detail_id' => $detail->id, 'status' => 'updated'];
                    }
                }

                $transaction->calculateTotalAmount();

                return response()->json([
                    'success' => true,
                    'message' => 'Batch quantity update completed',
                    'data' => $results
                ]);
            })->name('transaction-details.batch-quantities');

            // Batch apply discount
            Route::post('/discount', function (Request $request, Transaction $transaction) {
                $validator = Validator::make($request->all(), [
                    'detail_ids' => 'required|array|min:1',
                    'detail_ids.*' => 'exists:transaction_details,id',
                    'discount_percentage' => 'required|numeric|min:0|max:100',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'errors' => $validator->errors()
                    ], 422);
                }

                $results = [];
                foreach ($request->detail_ids as $detailId) {
                    $detail = TransactionDetail::where('id', $detailId)
                                             ->where('transaction_id', $transaction->id)
                                             ->first();
                    if ($detail) {
                        $detail->applyDiscount($request->discount_percentage);
                        $detail->save();
                        $results[] = [
                            'detail_id' => $detail->id,
                            'discount_applied' => $request->discount_percentage,
                            'discount_amount' => $detail->discount_amount
                        ];
                    }
                }

                $transaction->calculateTotalAmount();

                return response()->json([
                    'success' => true,
                    'message' => 'Batch discount applied successfully',
                    'data' => $results
                ]);
            })->name('transaction-details.batch-discount');

            // Batch QC update
            Route::post('/qc', function (Request $request, Transaction $transaction) {
                $validator = Validator::make($request->all(), [
                    'detail_ids' => 'required|array|min:1',
                    'detail_ids.*' => 'exists:transaction_details,id',
                    'qc_passed' => 'required|boolean',
                    'qc_notes' => 'nullable|string|max:500',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'errors' => $validator->errors()
                    ], 422);
                }

                $results = [];
                foreach ($request->detail_ids as $detailId) {
                    $detail = TransactionDetail::where('id', $detailId)
                                             ->where('transaction_id', $transaction->id)
                                             ->first();
                    if ($detail) {
                        $detail->update([
                            'qc_passed' => $request->qc_passed,
                            'qc_notes' => $request->qc_notes,
                        ]);
                        $results[] = [
                            'detail_id' => $detail->id,
                            'qc_status' => $detail->qc_passed ? 'Passed' : 'Failed'
                        ];
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Batch QC update completed',
                    'data' => $results
                ]);
            })->name('transaction-details.batch-qc');
        });

        /*
        |--------------------------------------------------------------------------
        | Query & Filter Operations
        |--------------------------------------------------------------------------
        */
        Route::prefix('query')->group(function () {
            
            // Get details by condition
            Route::get('/by-condition/{condition}', function (Transaction $transaction, $condition) {
                $validConditions = ['good', 'damaged', 'expired', 'returned'];
                if (!in_array($condition, $validConditions)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid condition'
                    ], 400);
                }

                $details = $transaction->transactionDetails()
                                     ->with(['item'])
                                     ->where('condition', $condition)
                                     ->get();

                return response()->json([
                    'success' => true,
                    'data' => $details->map(function ($detail) {
                        return [
                            'id' => $detail->id,
                            'line_number' => $detail->line_number,
                            'item_name' => $detail->item->name,
                            'quantity' => $detail->quantity,
                            'condition' => $detail->condition,
                        ];
                    })
                ]);
            })->name('transaction-details.by-condition');

            // Get details with variance
            Route::get('/with-variance', function (Transaction $transaction) {
                $details = $transaction->transactionDetails()
                                     ->with(['item'])
                                     ->hasVariance()
                                     ->get();

                return response()->json([
                    'success' => true,
                    'data' => $details->map(function ($detail) {
                        return [
                            'id' => $detail->id,
                            'line_number' => $detail->line_number,
                            'item_name' => $detail->item->name,
                            'planned_quantity' => $detail->quantity,
                            'actual_quantity' => $detail->actual_quantity,
                            'variance' => $detail->quantity_variance,
                            'variance_percentage' => $detail->variance_percentage,
                            'variance_reason' => $detail->variance_reason,
                        ];
                    })
                ]);
            })->name('transaction-details.with-variance');

            // Get QC failed details
            Route::get('/qc-failed', function (Transaction $transaction) {
                $details = $transaction->transactionDetails()
                                     ->with(['item'])
                                     ->where('qc_passed', false)
                                     ->get();

                return response()->json([
                    'success' => true,
                    'data' => $details->map(function ($detail) {
                        return [
                            'id' => $detail->id,
                            'line_number' => $detail->line_number,
                            'item_name' => $detail->item->name,
                            'quantity' => $detail->quantity,
                            'condition' => $detail->condition,
                            'qc_notes' => $detail->qc_notes,
                        ];
                    })
                ]);
            })->name('transaction-details.qc-failed');

            // Search details by item
            Route::get('/search', function (Request $request, Transaction $transaction) {
                $search = $request->get('q');
                if (!$search) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Search query required'
                    ], 400);
                }

                $details = $transaction->transactionDetails()
                                     ->with(['item'])
                                     ->whereHas('item', function ($query) use ($search) {
                                         $query->where('name', 'like', "%{$search}%")
                                               ->orWhere('sku', 'like', "%{$search}%");
                                     })
                                     ->orWhere('batch_number', 'like', "%{$search}%")
                                     ->orWhere('serial_number', 'like', "%{$search}%")
                                     ->get();

                return response()->json([
                    'success' => true,
                    'data' => $details->map(function ($detail) {
                        return [
                            'id' => $detail->id,
                            'line_number' => $detail->line_number,
                            'item_name' => $detail->item->name,
                            'item_sku' => $detail->item->sku,
                            'batch_number' => $detail->batch_number,
                            'quantity' => $detail->quantity,
                        ];
                    })
                ]);
            })->name('transaction-details.search');
        });

        /*
        |--------------------------------------------------------------------------
        | Summary & Statistics
        |--------------------------------------------------------------------------
        */
        Route::get('/summary', function (Transaction $transaction) {
            $details = $transaction->transactionDetails()->get();
            
            $summary = [
                'total_lines' => $details->count(),
                'total_quantity' => $details->sum('quantity'),
                'total_amount' => $details->sum('total_price'),
                'total_discount' => $details->sum('discount_amount'),
                'total_tax' => $details->sum('tax_amount'),
                'net_amount' => $details->sum('net_total_price'),
                'unique_items' => $details->pluck('item_id')->unique()->count(),
                'qc_status' => [
                    'passed' => $details->where('qc_passed', true)->count(),
                    'failed' => $details->where('qc_passed', false)->count(),
                    'pending' => $details->whereNull('qc_passed')->count(),
                ],
                'condition_breakdown' => [
                    'good' => $details->where('condition', 'good')->count(),
                    'damaged' => $details->where('condition', 'damaged')->count(),
                    'expired' => $details->where('condition', 'expired')->count(),
                    'returned' => $details->where('condition', 'returned')->count(),
                ],
                'variance_summary' => [
                    'has_variance' => $details->filter(function ($detail) {
                        return $detail->quantity_variance !== null && $detail->quantity_variance != 0;
                    })->count(),
                    'total_variance' => $details->sum('quantity_variance'),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        })->name('transaction-details.summary');

        /*
        |--------------------------------------------------------------------------
        | Export Operations
        |--------------------------------------------------------------------------
        */
        Route::get('/export/{format}', function (Transaction $transaction, $format) {
            if (!in_array($format, ['csv', 'xlsx', 'pdf'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid export format'
                ], 400);
            }

            // Implementation untuk export akan dibuat terpisah
            return response()->json([
                'success' => false,
                'message' => 'Export functionality not implemented yet',
                'todo' => "Implement export to {$format} format"
            ], 501);
        })->name('transaction-details.export');
    });
});

/*
|--------------------------------------------------------------------------
| Fallback Routes
|--------------------------------------------------------------------------
*/
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found',
        'documentation' => url('/api/v1/docs'),
        'available_endpoints' => [
            'GET /api/health' => 'Health check',
            'GET /api/v1/docs' => 'API documentation',
            'POST /api/v1/auth/login' => 'User authentication',
            'GET /api/v1/warehouses' => 'Warehouse management (protected)',
            'GET /api/v1/items' => 'Item management (protected)',
            'GET /api/v1/transactions' => 'Transaction management (protected)',
            'GET /api/v1/stocks' => 'Stock management (protected)',
            'GET /api/v1/reports/dashboard' => 'Dashboard reports (admin/manager)',
        ],
        'note' => 'Most endpoints require authentication. Use POST /api/v1/auth/login to get access token.'
    ], 404);
});

/*
|--------------------------------------------------------------------------
| Route Model Binding dengan Custom Logic
|--------------------------------------------------------------------------
*/

// Custom route model binding untuk warehouse dengan code support
Route::bind('warehouse', function ($value) {
    return \App\Models\Warehouse::where('id', $value)
                                ->orWhere('code', $value)
                                ->firstOrFail();
});

// Custom route model binding untuk item dengan SKU support
Route::bind('item', function ($value) {
    return \App\Models\Item::where('id', $value)
                          ->orWhere('sku', $value)
                          ->firstOrFail();
});

// Custom route model binding untuk transaction dengan transaction_number support
Route::bind('transaction', function ($value) {
    return \App\Models\Transaction::where('id', $value)
                                 ->orWhere('transaction_number', $value)
                                 ->firstOrFail();
});

// Custom route model binding untuk vendor dengan code support
Route::bind('vendor', function ($value) {
    return \App\Models\Vendor::where('id', $value)
                            ->orWhere('code', $value)
                            ->firstOrFail();
});

// Custom route model binding untuk TransactionDetail
Route::bind('detail', function ($value) {
    return \App\Models\TransactionDetail::findOrFail($value);
});