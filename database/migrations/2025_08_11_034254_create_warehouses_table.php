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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();

            //warehouse details
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            //data lokasi gudang
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            //informasi kapasitas dan status gudang
            $table->decimal('capacity', 15, 2)->nullable();
            $table->string('capacity_unit')->default('m3');
            $table->enum('status', ['active', 'inactive'])->default('active');

            //field contact person
            $table->string('manager_name')->nullable();
            $table->string('manager_email')->nullable();
            $table->string('manager_phone')->nullable();

            
            //timestamps and soft deletes
            $table->softDeletes();
            $table->timestamps();


            //indexes
            $table->index('code');
            $table->index('status');
            $table->index(['city', 'province']);
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
