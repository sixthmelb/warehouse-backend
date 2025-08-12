<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk tabel transactions - Fixed untuk konsep serah terima
 * Menyimpan header transaksi serah terima barang warehouse ke department/user
 * 
 * File: database/migrations/2025_08_11_040548_create_transactions_table.php
 */
return new class extends Migration
{
    /**
     * Jalankan migration untuk membuat tabel transactions
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            // Primary key
            $table->id();
            
            // Nomor dan identifikasi transaksi
            $table->string('transaction_number')->unique(); // Nomor transaksi (auto generate)
            $table->string('reference_number')->nullable(); // Nomor referensi eksternal (PO, Request, dll)
            
            // Jenis transaksi serah terima
            $table->enum('type', ['RECEIVE', 'ISSUE']); // RECEIVE = Terima dari vendor, ISSUE = Serahkan ke dept/user
            $table->enum('sub_type', [
                // RECEIVE types - barang masuk ke warehouse
                'purchase_receive', 'return_receive', 'transfer_receive', 'adjustment_receive',
                // ISSUE types - barang keluar dari warehouse ke user/dept
                'department_issue', 'user_issue', 'project_issue', 'maintenance_issue', 'return_issue', 'transfer_issue'
            ]);
            
            // Relasi dengan entitas
            $table->unsignedBigInteger('warehouse_id'); // Gudang terkait
            $table->string('vendor_name')->nullable(); // Nama vendor (untuk RECEIVE)
            $table->string('vendor_contact')->nullable(); // Kontak vendor
            
            // Penerima (untuk ISSUE transactions)
            $table->string('recipient_type')->nullable(); // 'department', 'user', 'project'
            $table->string('recipient_department')->nullable(); // Department penerima
            $table->string('recipient_name')->nullable(); // Nama penerima
            $table->string('recipient_employee_id')->nullable(); // Employee ID penerima
            $table->string('recipient_phone')->nullable(); // Telepon penerima
            $table->string('recipient_email')->nullable(); // Email penerima
            
            // Request information (untuk ISSUE)
            $table->string('request_number')->nullable(); // Nomor request dari department/user
            $table->text('request_purpose')->nullable(); // Tujuan request
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            
            // Project information (jika untuk project)
            $table->string('project_code')->nullable();
            $table->string('project_name')->nullable();
            $table->string('cost_center')->nullable();
            
            // Users workflow
            $table->unsignedBigInteger('created_by'); // User yang membuat
            $table->unsignedBigInteger('approved_by')->nullable(); // User yang approve
            $table->unsignedBigInteger('issued_by')->nullable(); // User yang mengeluarkan barang
            $table->unsignedBigInteger('received_by')->nullable(); // User yang menerima barang
            
            // Tanggal workflow
            $table->date('transaction_date'); // Tanggal transaksi
            $table->datetime('requested_date')->nullable(); // Tanggal request
            $table->datetime('approved_date')->nullable(); // Tanggal approval
            $table->datetime('issued_date')->nullable(); // Tanggal barang dikeluarkan
            $table->datetime('received_date')->nullable(); // Tanggal barang diterima
            
            // Status workflow
            $table->enum('status', ['draft', 'requested', 'approved', 'issued', 'received', 'completed', 'cancelled'])
                  ->default('draft');
            
            // Return management
            $table->boolean('is_returnable')->default(false); // Apakah barang harus dikembalikan
            $table->date('return_due_date')->nullable(); // Tanggal deadline return
            $table->enum('return_status', ['not_required', 'pending', 'partial', 'completed', 'overdue'])->nullable();
            
            // Delivery information
            $table->text('delivery_address')->nullable();
            $table->string('delivery_method')->nullable(); // pickup, delivery, courier
            $table->string('tracking_number')->nullable();
            
            // Financial (opsional untuk cost tracking)
            $table->decimal('total_estimated_value', 15, 2)->nullable();
            $table->decimal('total_actual_value', 15, 2)->nullable();
            $table->string('currency', 3)->default('IDR');
            
            // Documents dan approval
            $table->json('documents')->nullable(); // Array dokumen pendukung
            $table->text('notes')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('issue_notes')->nullable();
            $table->text('receive_notes')->nullable();
            
            // Special handling
            $table->boolean('is_emergency')->default(false);
            $table->text('emergency_reason')->nullable();
            $table->boolean('requires_inspection')->default(false);
            
            // Metadata
            $table->json('metadata')->nullable();
            
            // Audit trail
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key constraints
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('issued_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('received_by')->references('id')->on('users')->onDelete('restrict');
            
            // Indexes
            $table->index('transaction_number');
            $table->index('type');
            $table->index('sub_type');
            $table->index('status');
            $table->index('warehouse_id');
            $table->index('recipient_department');
            $table->index('project_code');
            $table->index('transaction_date');
            $table->index('priority');
            $table->index(['type', 'status']);
            $table->index(['warehouse_id', 'type']);
            $table->index(['recipient_department', 'status']);
        });
    }

    /**
     * Rollback migration
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};