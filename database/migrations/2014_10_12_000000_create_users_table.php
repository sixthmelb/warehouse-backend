<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            //Data Personal
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            //Field Tambahan untuk warehouse management
            $table->string('employee_id')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->enum('role', ['admin', 'manager', 'staff'])->default('staff');
            $table->enum('status', ['active', 'inactive'])->default('active');

            //Field Untuk relasi
            $table->json('warehouse_access')->nullable();

            $table->rememberToken();

            $table->timestamps();
            $table->softDeletes();

            //indexes
            $table->index('employee_id');
            $table->index('role');
            $table->index('status');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
