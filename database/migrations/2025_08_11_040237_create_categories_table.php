<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk tabel categories
 * Menyimpan kategori-kategori item untuk organisasi barang di warehouse
 * Support hierarchical categories (parent-child)
 * 
 * Command: php artisan make:migration create_categories_table
 */
return new class extends Migration
{
    /**
     * Jalankan migration untuk membuat tabel categories
     * Tabel ini menyimpan master data kategori barang
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            // Primary key
            $table->id();
            
            // Data utama kategori
            $table->string('code')->unique(); // Kode kategori (contoh: CAT001, CAT002)
            $table->string('name'); // Nama kategori
            $table->text('description')->nullable(); // Deskripsi kategori
            
            // Hierarchical structure (support parent-child categories)
            $table->unsignedBigInteger('parent_id')->nullable(); // ID parent category
            $table->integer('level')->default(1); // Level hierarchy (1 = root, 2 = child, dst)
            $table->string('path')->nullable(); // Path hierarchy (contoh: "1/2/5" untuk breadcrumb)
            
            // Informasi tambahan
            $table->string('icon')->nullable(); // Icon untuk UI (nama icon atau path)
            $table->string('color')->nullable(); // Warna kategori untuk UI (hex color)
            $table->integer('sort_order')->default(0); // Urutan tampilan
            
            // Status dan visibility
            $table->boolean('is_active')->default(true); // Status aktif/tidak
            $table->boolean('is_visible')->default(true); // Visibility di frontend
            
            // Metadata untuk business logic
            $table->json('attributes')->nullable(); // Custom attributes untuk kategori (JSON)
            $table->text('notes')->nullable(); // Catatan internal
            
            // Timestamp untuk audit trail
            $table->timestamps(); // created_at, updated_at
            $table->softDeletes(); // deleted_at untuk soft delete
            
            // Foreign key constraint untuk parent category
            $table->foreign('parent_id')->references('id')->on('categories')
                  ->onDelete('set null'); // Jika parent dihapus, set null
            
            // Index untuk optimasi query
            $table->index('code');
            $table->index('name');
            $table->index('parent_id');
            $table->index('level');
            $table->index('is_active');
            $table->index('sort_order');
            $table->index(['parent_id', 'sort_order']); // Composite index untuk sorting
        });
    }

    /**
     * Rollback migration - hapus tabel categories
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};