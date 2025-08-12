<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk tabel transaction_details - Fixed untuk serah terima
 * Menyimpan detail item dalam setiap transaksi serah terima warehouse
 * 
 * File: database/migrations/2025_08_11_040618_create_transaction_details_table.php
 */
return new class extends Migration
{
    /**
     * Jalankan migration untuk membuat tabel transaction_details
     */
    public function up(): void
    {
        Schema::create('transaction_details', function (Blueprint $table) {
            // Primary key
            $table->id();
            
            // Relasi dengan transaksi dan item
            $table->unsignedBigInteger('transaction_id'); // Foreign key ke transactions
            $table->unsignedBigInteger('item_id'); // Foreign key ke items
            
            // Informasi quantity dan unit untuk serah terima
            $table->decimal('requested_quantity', 15, 2); // Jumlah yang diminta
            $table->decimal('approved_quantity', 15, 2)->nullable(); // Jumlah yang disetujui
            $table->decimal('issued_quantity', 15, 2)->nullable(); // Jumlah yang dikeluarkan
            $table->decimal('received_quantity', 15, 2)->nullable(); // Jumlah yang diterima
            $table->string('unit'); // Satuan (pcs, kg, liter, dll)
            $table->decimal('unit_weight', 8, 2)->nullable(); // Berat per unit
            
            // Batch dan serial tracking
            $table->string('batch_number')->nullable(); // Nomor batch barang
            $table->string('serial_number')->nullable(); // Serial number
            $table->date('expiry_date')->nullable(); // Tanggal expired
            $table->date('manufacture_date')->nullable(); // Tanggal produksi
            
            // Pricing information (untuk tracking cost)
            $table->decimal('unit_cost', 15, 2)->nullable(); // Harga per unit
            $table->decimal('total_cost', 15, 2)->nullable(); // Total cost
            
            // Request details (untuk ISSUE transactions)
            $table->text('request_reason')->nullable(); // Alasan request item ini
            $table->date('needed_date')->nullable(); // Tanggal dibutuhkan
            $table->enum('urgency', ['low', 'normal', 'high', 'critical'])->default('normal');
            $table->string('usage_purpose')->nullable(); // Tujuan penggunaan
            
            // Storage location dalam warehouse
            $table->string('storage_location')->nullable(); // Lokasi penyimpanan (rak, zona, dll)
            $table->string('storage_zone')->nullable(); // Zone storage
            $table->string('storage_rack')->nullable(); // Nomor rak
            $table->string('storage_level')->nullable(); // Level rak
            
            // Quality control dan kondisi barang
            $table->enum('condition', ['new', 'good', 'fair', 'damaged'])->default('good');
            $table->text('condition_notes')->nullable(); // Catatan kondisi
            $table->boolean('qc_passed')->default(true); // QC lolos atau tidak
            $table->text('qc_notes')->nullable(); // Catatan QC
            
            // Return management (untuk barang yang harus dikembalikan)
            $table->boolean('is_returnable')->default(false); // Harus dikembalikan
            $table->date('return_due_date')->nullable(); // Deadline return
            $table->enum('return_condition_expected', ['same', 'good', 'any'])->default('same');
            $table->boolean('is_returned')->default(false); // Sudah dikembalikan
            $table->date('actual_return_date')->nullable(); // Tanggal actual return
            $table->decimal('returned_quantity', 15, 2)->nullable(); // Quantity yang dikembalikan
            
            // Informasi tambahan
            $table->integer('line_number')->default(1); // Nomor urut line
            $table->text('notes')->nullable(); // Catatan spesifik line item
            $table->json('custom_attributes')->nullable(); // Custom attributes
            
            // Audit trail
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key constraints
            $table->foreign('transaction_id')->references('id')->on('transactions')
                  ->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('items')
                  ->onDelete('restrict');
            
            // Index untuk optimasi query
            $table->index('transaction_id');
            $table->index('item_id');
            $table->index('batch_number');
            $table->index('serial_number');
            $table->index('expiry_date');
            $table->index('condition');
            $table->index('storage_location');
            $table->index('line_number');
            $table->index('is_returnable');
            $table->index('return_due_date');
            
            // Composite indexes
            $table->index(['transaction_id', 'line_number']);
            $table->index(['item_id', 'batch_number']);
            $table->index(['storage_location', 'storage_zone']);
            
            // Unique constraint untuk line number per transaksi
            $table->unique(['transaction_id', 'line_number'], 'unique_transaction_line');
        });
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_details');
    }
};