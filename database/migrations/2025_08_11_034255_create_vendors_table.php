<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk tabel vendors
 * Menyimpan data supplier/vendor yang menyediakan barang ke warehouse
 * 
 * Command: php artisan make:migration create_vendors_table
 */
return new class extends Migration
{
    /**
     * Jalankan migration untuk membuat tabel vendors
     * Tabel ini menyimpan master data vendor/supplier
     */
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            // Primary key
            $table->id();
            
            // Data utama vendor
            $table->string('code')->unique(); // Kode vendor (contoh: VND001, VND002)
            $table->string('name'); // Nama perusahaan vendor
            $table->text('description')->nullable(); // Deskripsi vendor
            
            // Data kontak vendor
            $table->text('address'); // Alamat lengkap vendor
            $table->string('city'); // Kota vendor
            $table->string('province'); // Provinsi vendor
            $table->string('postal_code')->nullable(); // Kode pos
            $table->string('phone')->nullable(); // Telepon kantor
            $table->string('fax')->nullable(); // Fax (jika ada)
            $table->string('email')->nullable(); // Email perusahaan
            $table->string('website')->nullable(); // Website perusahaan
            
            // Data legal dan bisnis
            $table->string('tax_number')->nullable(); // NPWP/Tax ID
            $table->string('business_license')->nullable(); // Nomor izin usaha
            $table->enum('vendor_type', ['supplier', 'distributor', 'manufacturer', 'other'])
                  ->default('supplier'); // Jenis vendor
            
            // Contact person
            $table->string('contact_person_name')->nullable(); // Nama PIC
            $table->string('contact_person_phone')->nullable(); // Telepon PIC
            $table->string('contact_person_email')->nullable(); // Email PIC
            $table->string('contact_person_position')->nullable(); // Jabatan PIC
            
            // Payment terms dan business info
            $table->integer('payment_terms_days')->default(30); // Termin pembayaran (hari)
            $table->enum('payment_method', ['cash', 'transfer', 'check', 'credit'])
                  ->default('transfer'); // Metode pembayaran
            $table->decimal('credit_limit', 15, 2)->nullable(); // Limit kredit
            $table->enum('status', ['active', 'inactive', 'blacklist'])->default('active'); // Status vendor
            
            // Rating dan performance
            $table->decimal('rating', 3, 2)->nullable(); // Rating vendor (1.00-5.00)
            $table->text('notes')->nullable(); // Catatan tambahan
            
            // Timestamp untuk audit trail
            $table->timestamps(); // created_at, updated_at
            $table->softDeletes(); // deleted_at untuk soft delete
            
            // Index untuk optimasi query
            $table->index('code');
            $table->index('name');
            $table->index('status');
            $table->index('vendor_type');
            $table->index('city');
            $table->index('tax_number');
        });
    }

    /**
     * Rollback migration - hapus tabel vendors
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};