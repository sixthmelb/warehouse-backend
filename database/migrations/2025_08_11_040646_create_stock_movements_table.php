<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk tabel stock_movements
 * Menyimpan audit trail semua pergerakan stok barang di warehouse
 * Tabel ini penting untuk tracking history dan audit
 * 
 * Command: php artisan make:migration create_stock_movements_table
 */
return new class extends Migration
{
    /**
     * Jalankan migration untuk membuat tabel stock_movements
     * Tabel ini berfungsi sebagai audit trail untuk semua pergerakan stok
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            // Primary key
            $table->id();
            
            // Relasi dengan entitas terkait
            $table->unsignedBigInteger('item_id'); // Item yang mengalami pergerakan stok
            $table->unsignedBigInteger('warehouse_id'); // Warehouse lokasi pergerakan
            $table->unsignedBigInteger('transaction_id')->nullable(); // Transaksi penyebab movement
            $table->unsignedBigInteger('transaction_detail_id')->nullable(); // Detail transaksi spesifik
            $table->unsignedBigInteger('user_id'); // User yang melakukan pergerakan
            
            // Informasi pergerakan stok
            $table->enum('movement_type', ['IN', 'OUT']); // Tipe pergerakan: masuk atau keluar
            $table->string('movement_reason'); // Alasan pergerakan (purchase, sale, adjustment, dll)
            $table->decimal('quantity_before', 15, 2); // Stok sebelum pergerakan
            $table->decimal('quantity_moved', 15, 2); // Jumlah yang dipindahkan
            $table->decimal('quantity_after', 15, 2); // Stok setelah pergerakan
            $table->string('unit'); // Satuan quantity
            
            // Batch dan serial tracking untuk audit
            $table->string('batch_number')->nullable(); // Batch number yang bergerak
            $table->string('serial_number')->nullable(); // Serial number yang bergerak
            $table->date('expiry_date')->nullable(); // Expiry date item yang bergerak
            
            // Lokasi storage
            $table->string('location_from')->nullable(); // Lokasi asal (untuk transfer internal)
            $table->string('location_to')->nullable(); // Lokasi tujuan (untuk transfer internal)
            $table->string('storage_zone')->nullable(); // Zone penyimpanan
            
            // Informasi finansial snapshot
            $table->decimal('unit_cost', 15, 2)->nullable(); // Biaya per unit saat movement
            $table->decimal('total_cost', 15, 2)->nullable(); // Total biaya movement
            $table->string('currency', 3)->default('IDR'); // Mata uang
            
            // Reference dan dokumentasi
            $table->string('reference_number')->nullable(); // Nomor referensi external
            $table->string('document_number')->nullable(); // Nomor dokumen pendukung
            $table->text('notes')->nullable(); // Catatan tambahan
            
            // Approval dan validasi
            $table->boolean('is_approved')->default(false); // Status approval movement
            $table->unsignedBigInteger('approved_by')->nullable(); // User yang approve
            $table->datetime('approved_at')->nullable(); // Waktu approval
            
            // Koreksi dan adjustment
            $table->boolean('is_correction')->default(false); // Apakah ini koreksi dari movement sebelumnya
            $table->unsignedBigInteger('corrected_movement_id')->nullable(); // ID movement yang dikoreksi
            $table->text('correction_reason')->nullable(); // Alasan koreksi
            
            // Automated vs Manual tracking
            $table->boolean('is_automated')->default(false); // Movement otomatis dari sistem vs manual
            $table->string('source_system')->nullable(); // Source system jika automated (API, import, dll)
            
            // Timestamp detail untuk audit
            $table->datetime('movement_date'); // Tanggal aktual pergerakan
            $table->timestamps(); // created_at, updated_at (kapan record dibuat di sistem)
            
            // Tidak pakai soft delete untuk audit trail - data harus permanen
            
            // Foreign key constraints
            $table->foreign('item_id')->references('id')->on('items')
                  ->onDelete('restrict');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')
                  ->onDelete('restrict');
            $table->foreign('transaction_id')->references('id')->on('transactions')
                  ->onDelete('set null'); // Jika transaksi dihapus, tetap simpan audit trail
            $table->foreign('transaction_detail_id')->references('id')->on('transaction_details')
                  ->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')
                  ->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')
                  ->onDelete('restrict');
            $table->foreign('corrected_movement_id')->references('id')->on('stock_movements')
                  ->onDelete('set null');
            
            // Index untuk optimasi query audit dan reporting
            $table->index('item_id');
            $table->index('warehouse_id');
            $table->index('transaction_id');
            $table->index('user_id');
            $table->index('movement_type');
            $table->index('movement_reason');
            $table->index('movement_date');
            $table->index('batch_number');
            $table->index('serial_number');
            $table->index('is_approved');
            $table->index('is_correction');
            $table->index('is_automated');
            
            // Composite indexes untuk query reporting yang kompleks
            $table->index(['item_id', 'warehouse_id', 'movement_date']); // Stock movement per item per warehouse
            $table->index(['warehouse_id', 'movement_date', 'movement_type']); // Movement per warehouse per type
            $table->index(['item_id', 'batch_number', 'movement_date']); // Batch tracking
            $table->index(['transaction_id', 'movement_type']); // Movement per transaction
            $table->index(['movement_date', 'is_approved']); // Approved movements per date
        });
    }

    /**
     * Rollback migration - hapus tabel stock_movements
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};