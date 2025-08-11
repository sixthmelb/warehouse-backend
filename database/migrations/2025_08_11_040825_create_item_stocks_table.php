<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk tabel item_stocks
 * Menyimpan current stock level untuk setiap item di setiap warehouse
 * Tabel ini di-update real-time setiap ada stock movement
 * 
 * Command: php artisan make:migration create_item_stocks_table
 */
return new class extends Migration
{
    /**
     * Jalankan migration untuk membuat tabel item_stocks
     * Tabel ini menyimpan current stock level per item per warehouse
     */
    public function up(): void
    {
        Schema::create('item_stocks', function (Blueprint $table) {
            // Primary key
            $table->id();
            
            // Relasi utama - kombinasi item + warehouse harus unique
            $table->unsignedBigInteger('item_id'); // Item yang di-stock
            $table->unsignedBigInteger('warehouse_id'); // Warehouse lokasi stock
            
            // Current stock information
            $table->decimal('current_stock', 15, 2)->default(0); // Stok saat ini
            $table->decimal('reserved_stock', 15, 2)->default(0); // Stok yang direserve/allocated
            $table->decimal('available_stock', 15, 2)->default(0); // Stok available (current - reserved)
            $table->string('unit'); // Satuan stock (copy dari items untuk performa)
            
            // Stock level indicators
            $table->decimal('minimum_stock', 15, 2)->default(0); // Minimum stock level
            $table->decimal('maximum_stock', 15, 2)->nullable(); // Maximum stock level
            $table->decimal('reorder_point', 15, 2)->nullable(); // Reorder point
            $table->decimal('reorder_quantity', 15, 2)->nullable(); // Quantity untuk reorder
            
            // Stock valuation (untuk accounting)
            $table->decimal('average_cost', 15, 2)->default(0); // Average cost per unit
            $table->decimal('last_cost', 15, 2)->default(0); // Last purchase cost per unit
            $table->decimal('total_value', 15, 2)->default(0); // Total value (current_stock * average_cost)
            $table->string('currency', 3)->default('IDR'); // Currency untuk costing
            
            // Location dalam warehouse
            $table->string('primary_location')->nullable(); // Lokasi utama penyimpanan
            $table->string('storage_zone')->nullable(); // Zone penyimpanan utama
            $table->json('storage_locations')->nullable(); // Array semua lokasi jika tersebar
            
            // Batch and expiry tracking summary
            $table->integer('total_batches')->default(0); // Total batch yang ada
            $table->date('earliest_expiry')->nullable(); // Expiry date paling dekat
            $table->decimal('expiring_stock', 15, 2)->default(0); // Stock yang akan expired dalam 30 hari
            
            // Stock movement tracking
            $table->datetime('last_movement_date')->nullable(); // Tanggal movement terakhir
            $table->string('last_movement_type')->nullable(); // Type movement terakhir (IN/OUT)
            $table->decimal('last_movement_quantity', 15, 2)->default(0); // Quantity movement terakhir
            
            // Stock status dan alert
            $table->enum('stock_status', ['normal', 'low', 'out_of_stock', 'overstock'])
                  ->default('normal'); // Status level stock
            $table->boolean('reorder_alert')->default(false); // Alert untuk reorder
            $table->boolean('expiry_alert')->default(false); // Alert untuk expiry
            $table->text('alert_notes')->nullable(); // Catatan alert
            
            // Stock counting dan cycle count
            $table->date('last_count_date')->nullable(); // Tanggal stock opname terakhir
            $table->decimal('last_count_quantity', 15, 2)->nullable(); // Hasil count terakhir
            $table->decimal('count_variance', 15, 2)->default(0); // Variance dari count terakhir
            $table->datetime('next_count_due')->nullable(); // Jadwal count berikutnya
            
            // Metadata
            $table->json('metadata')->nullable(); // Additional data dalam JSON format
            $table->text('notes')->nullable(); // Catatan khusus untuk stock item ini
            
            // Audit trail
            $table->timestamps(); // created_at, updated_at
            // Tidak pakai soft delete karena ini master stock data
            
            // Foreign key constraints
            $table->foreign('item_id')->references('id')->on('items')
                  ->onDelete('cascade'); // Jika item dihapus, stock record ikut dihapus
            $table->foreign('warehouse_id')->references('id')->on('warehouses')
                  ->onDelete('cascade'); // Jika warehouse dihapus, stock record ikut dihapus
            
            // Unique constraint - satu item hanya boleh punya satu record per warehouse
            $table->unique(['item_id', 'warehouse_id'], 'unique_item_warehouse_stock');
            
            // Index untuk optimasi query
            $table->index('item_id');
            $table->index('warehouse_id');
            $table->index('current_stock');
            $table->index('available_stock');
            $table->index('stock_status');
            $table->index('reorder_alert');
            $table->index('expiry_alert');
            $table->index('earliest_expiry');
            $table->index('last_movement_date');
            $table->index('last_count_date');
            $table->index('next_count_due');
            
            // Composite indexes untuk query yang kompleks
            $table->index(['warehouse_id', 'stock_status']); // Stock status per warehouse
            $table->index(['item_id', 'stock_status']); // Stock status per item across warehouses
            $table->index(['warehouse_id', 'reorder_alert']); // Reorder alerts per warehouse
            $table->index(['warehouse_id', 'expiry_alert']); // Expiry alerts per warehouse
            $table->index(['current_stock', 'minimum_stock']); // Low stock detection
            $table->index(['earliest_expiry', 'expiry_alert']); // Expiry monitoring
        });
    }

    /**
     * Rollback migration - hapus tabel item_stocks
     */
    public function down(): void
    {
        Schema::dropIfExists('item_stocks');
    }
};