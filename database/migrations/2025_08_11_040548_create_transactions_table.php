<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk tabel transactions
 * Menyimpan header transaksi barang masuk/keluar warehouse
 * 
 * Command: php artisan make:migration create_transactions_table
 */
return new class extends Migration
{
    /**
     * Jalankan migration untuk membuat tabel transactions
     * Tabel ini menyimpan header/master data transaksi
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            // Primary key
            $table->id();
            
            // Nomor dan identifikasi transaksi
            $table->string('transaction_number')->unique(); // Nomor transaksi (auto generate)
            $table->string('reference_number')->nullable(); // Nomor referensi eksternal (PO, SO, dll)
            
            // Jenis transaksi
            $table->enum('type', ['IN', 'OUT']); // IN = Barang Masuk, OUT = Barang Keluar
            $table->enum('sub_type', [
                'purchase', 'return_from_customer', 'adjustment_in', 'transfer_in',
                'sale', 'return_to_vendor', 'adjustment_out', 'transfer_out', 'damaged'
            ]); // Sub kategori transaksi untuk lebih spesifik
            
            // Relasi dengan entitas lain
            $table->unsignedBigInteger('warehouse_id'); // Gudang terkait transaksi
            $table->unsignedBigInteger('vendor_id')->nullable(); // Vendor (untuk transaksi IN dari pembelian)
            $table->unsignedBigInteger('created_by'); // User yang membuat transaksi
            $table->unsignedBigInteger('approved_by')->nullable(); // User yang menyetujui transaksi
            
            // Tanggal dan waktu
            $table->date('transaction_date'); // Tanggal transaksi
            $table->datetime('planned_date')->nullable(); // Tanggal rencana eksekusi
            $table->datetime('executed_date')->nullable(); // Tanggal actual eksekusi
            
            // Status transaksi
            $table->enum('status', ['draft', 'pending', 'approved', 'executed', 'cancelled'])
                  ->default('draft');
            
            // Informasi finansial (opsional)
            $table->decimal('total_amount', 15, 2)->nullable(); // Total nilai transaksi
            $table->decimal('tax_amount', 15, 2)->nullable(); // Jumlah pajak
            $table->decimal('discount_amount', 15, 2)->nullable(); // Jumlah diskon
            $table->string('currency', 3)->default('IDR'); // Mata uang
            
            // Informasi pengiriman/penerima (untuk transaksi OUT)
            $table->string('recipient_name')->nullable(); // Nama penerima barang
            $table->string('recipient_phone')->nullable(); // Telepon penerima
            $table->text('delivery_address')->nullable(); // Alamat pengiriman
            $table->string('delivery_method')->nullable(); // Metode pengiriman
            $table->string('tracking_number')->nullable(); // Nomor resi pengiriman
            
            // Dokumen pendukung
            $table->json('documents')->nullable(); // Array dokumen pendukung (JSON)
            $table->text('notes')->nullable(); // Catatan transaksi
            $table->text('approval_notes')->nullable(); // Catatan approval
            $table->text('execution_notes')->nullable(); // Catatan eksekusi
            
            // Metadata
            $table->json('metadata')->nullable(); // Data tambahan dalam format JSON
            
            // Audit trail
            $table->timestamps(); // created_at, updated_at
            $table->softDeletes(); // deleted_at untuk soft delete
            
            // Foreign key constraints
            $table->foreign('warehouse_id')->references('id')->on('warehouses')
                  ->onDelete('restrict');
            $table->foreign('vendor_id')->references('id')->on('vendors')
                  ->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')
                  ->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')
                  ->onDelete('restrict');
            
            // Index untuk optimasi query
            $table->index('transaction_number');
            $table->index('reference_number');
            $table->index('type');
            $table->index('sub_type');
            $table->index('status');
            $table->index('warehouse_id');
            $table->index('vendor_id');
            $table->index('created_by');
            $table->index('transaction_date');
            $table->index('planned_date');
            $table->index('executed_date');
            
            // Composite indexes untuk query yang sering digunakan
            $table->index(['warehouse_id', 'type', 'status']);
            $table->index(['transaction_date', 'type']);
            $table->index(['vendor_id', 'type']);
        });
    }

    /**
     * Rollback migration - hapus tabel transactions
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};