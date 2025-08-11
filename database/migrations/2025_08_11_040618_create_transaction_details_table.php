<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk tabel transaction_details
 * Menyimpan detail item dalam setiap transaksi warehouse
 * Relasi: One transaction has many transaction details
 * 
 * Command: php artisan make:migration create_transaction_details_table
 */
return new class extends Migration
{
    /**
     * Jalankan migration untuk membuat tabel transaction_details
     * Tabel ini menyimpan detail barang dalam setiap transaksi
     */
    public function up(): void
    {
        Schema::create('transaction_details', function (Blueprint $table) {
            // Primary key
            $table->id();
            
            // Relasi dengan transaksi dan item
            $table->unsignedBigInteger('transaction_id'); // Foreign key ke transactions
            $table->unsignedBigInteger('item_id'); // Foreign key ke items
            
            // Informasi quantity dan unit
            $table->decimal('quantity', 15, 2); // Jumlah barang (bisa decimal untuk liquid/weight items)
            $table->string('unit'); // Satuan (pcs, kg, liter, dll) - copy dari items untuk history
            $table->decimal('unit_weight', 8, 2)->nullable(); // Berat per unit (untuk kalkulasi)
            
            // Batch dan serial tracking
            $table->string('batch_number')->nullable(); // Nomor batch barang
            $table->string('serial_number')->nullable(); // Serial number untuk item yang memerlukan
            $table->date('expiry_date')->nullable(); // Tanggal expired (jika ada)
            $table->date('manufacture_date')->nullable(); // Tanggal produksi
            
            // Pricing information (snapshot pada saat transaksi)
            $table->decimal('unit_price', 15, 2)->nullable(); // Harga per unit saat transaksi
            $table->decimal('total_price', 15, 2)->nullable(); // Total harga (quantity x unit_price)
            $table->decimal('discount_percentage', 5, 2)->default(0); // Persentase diskon
            $table->decimal('discount_amount', 15, 2)->default(0); // Nominal diskon
            $table->decimal('tax_percentage', 5, 2)->default(0); // Persentase pajak
            $table->decimal('tax_amount', 15, 2)->default(0); // Nominal pajak
            
            // Storage location dalam warehouse
            $table->string('storage_location')->nullable(); // Lokasi penyimpanan (rak, zona, dll)
            $table->string('storage_zone')->nullable(); // Zone storage (A1, B2, dll)
            $table->string('storage_rack')->nullable(); // Nomor rak
            $table->string('storage_level')->nullable(); // Level rak (1, 2, 3, dll)
            
            // Quality control dan kondisi barang
            $table->enum('condition', ['good', 'damaged', 'expired', 'returned'])->default('good');
            $table->text('condition_notes')->nullable(); // Catatan kondisi barang
            $table->boolean('qc_passed')->default(true); // Apakah lolos quality control
            $table->text('qc_notes')->nullable(); // Catatan quality control
            
            // Informasi tambahan untuk tracking
            $table->integer('line_number')->default(1); // Nomor urut line dalam transaksi
            $table->decimal('actual_quantity', 15, 2)->nullable(); // Quantity actual (jika berbeda dari planned)
            $table->text('variance_reason')->nullable(); // Alasan jika ada perbedaan quantity
            
            // Metadata dan custom fields
            $table->json('custom_attributes')->nullable(); // Custom attributes per item
            $table->text('notes')->nullable(); // Catatan spesifik untuk line item ini
            
            // Audit trail
            $table->timestamps(); // created_at, updated_at
            $table->softDeletes(); // deleted_at untuk soft delete
            
            // Foreign key constraints
            $table->foreign('transaction_id')->references('id')->on('transactions')
                  ->onDelete('cascade'); // Jika transaksi dihapus, detail ikut terhapus
            $table->foreign('item_id')->references('id')->on('items')
                  ->onDelete('restrict'); // Tidak boleh hapus item jika masih ada di transaksi
            
            // Index untuk optimasi query
            $table->index('transaction_id');
            $table->index('item_id');
            $table->index('batch_number');
            $table->index('serial_number');
            $table->index('expiry_date');
            $table->index('condition');
            $table->index('storage_location');
            $table->index('line_number');
            
            // Composite indexes untuk query yang sering digunakan
            $table->index(['transaction_id', 'line_number']); // Untuk sorting line dalam transaksi
            $table->index(['item_id', 'batch_number']); // Untuk tracking batch per item
            $table->index(['item_id', 'expiry_date']); // Untuk monitoring expired items
            $table->index(['storage_location', 'storage_zone']); // Untuk pencarian lokasi
            
            // Unique constraint untuk mencegah duplicate line dalam transaksi yang sama
            $table->unique(['transaction_id', 'line_number'], 'unique_transaction_line');
        });
    }

    /**
     * Rollback migration - hapus tabel transaction_details
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_details');
    }
};