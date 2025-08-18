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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_id')->unique(); // Custom vehicle ID
            $table->string('plat_no')->unique(); // License plate number
            $table->string('brand'); // Vehicle brand (Toyota, Honda, etc.)
            $table->string('model'); // Vehicle model (Avanza, Civic, etc.)
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            // Indexes for better performance
            $table->index('status');
            $table->index('vehicle_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
