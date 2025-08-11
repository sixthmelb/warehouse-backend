<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk tabel items
 * Menyimpan master data barang/item yang ada di warehouse
 * 
 * Command: php artisan make:migration create_items_table
 */
return new class extends Migration
{
    /**
     * Jalankan migration untuk membuat tabel items
     * Tabel ini menyimpan master data barang
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            // Primary key
            $table->id();
            
            // Data utama item
            $table->string('sku')->unique(); // SKU (Stock Keeping Unit) - kode unik barang
            $table->string('barcode')->unique()->nullable(); // Barcode untuk scanning
            $table->string('name'); // Nama barang
            $table->text('description')->nullable(); // Deskripsi detail barang
            
            // Relasi dengan kategori
            $table->unsignedBigInteger('category_id'); // Foreign key ke categories
            
            // Informasi fisik barang
            $table->string('brand')->nullable(); // Merek barang
            $table->string('model')->nullable(); // Model barang
            $table->decimal('weight', 8, 2)->nullable(); // Berat barang (kg)
            $table->decimal('length', 8, 2)->nullable(); // Panjang (cm)
            $table->decimal('width', 8, 2)->nullable(); // Lebar (cm)
            $table->decimal('height', 8, 2)->nullable(); // Tinggi (cm)
            $table->string('color')->nullable(); // Warna barang
            $table->string('size')->nullable(); // Ukuran barang
            
            // Unit dan packaging
            $table->string('unit')->default('pcs'); // Satuan dasar (pcs, kg, liter, dll)
            $table->integer('items_per_package')->default(1); // Jumlah item per kemasan
            $table->string('package_type')->nullable(); // Jenis kemasan (box, carton, dll)
            
            // Pricing information
            $table->decimal('purchase_price', 15, 2)->nullable(); // Harga beli
            $table->decimal('selling_price', 15, 2)->nullable(); // Harga jual
            $table->string('currency', 3)->default('IDR'); // Mata uang
            
            // Inventory management
            $table->integer('minimum_stock')->default(0); // Stock minimum untuk alert
            $table->integer('maximum_stock')->nullable(); // Stock maksimum
            $table->integer('reorder_point')->nullable(); // Titik reorder otomatis
            $table->integer('reorder_quantity')->nullable(); // Jumlah reorder
            
            // Storage requirements
            $table->enum('storage_type', ['normal', 'cold', 'frozen', 'hazardous'])
                  ->default('normal'); // Jenis penyimpanan yang dibutuhkan
            $table->text('storage_notes')->nullable(); // Catatan khusus penyimpanan
            
            // Expiry and batch tracking
            $table->boolean('has_expiry')->default(false); // Apakah barang punya expire date
            $table->boolean('batch_tracking')->default(false); // Apakah perlu tracking batch
            $table->boolean('serial_tracking')->default(false); // Apakah perlu tracking serial number
            
            // Status dan metadata
            $table->enum('status', ['active', 'inactive', 'discontinued'])->default('active');
            $table->json('custom_fields')->nullable(); // Custom fields untuk kebutuhan spesifik
            $table->text('notes')->nullable(); // Catatan tambahan
            $table->string('image_url')->nullable(); // URL gambar barang
            
            // Audit trail
            $table->timestamps(); // created_at, updated_at
            $table->softDeletes(); // deleted_at untuk soft delete
            
            // Foreign key constraints
            $table->foreign('category_id')->references('id')->on('categories')
                  ->onDelete('restrict'); // Tidak boleh hapus kategori jika masih ada item
            
            // Index untuk optimasi query
            $table->index('sku');
            $table->index('barcode');
            $table->index('name');
            $table->index('category_id');
            $table->index('brand');
            $table->index('status');
            $table->index('storage_type');
            $table->index(['category_id', 'status']); // Composite index
            $table->index(['brand', 'status']); // Composite index
        });
    }

    /**
     * Rollback migration - hapus tabel items
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};