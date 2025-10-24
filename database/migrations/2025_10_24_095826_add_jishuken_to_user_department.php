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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('department', ['Accounting', 'Marketing', 'Purchasing', 'QC & Engineering', 'Maintenance', 'HR & GA', 'Brazing', 'Chassis', 'Nylon', 'PPIC', 'Jishuken'])->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('department', ['Accounting', 'Marketing', 'Purchasing', 'QC & Engineering', 'Maintenance', 'HR & GA', 'Brazing', 'Chassis', 'Nylon', 'PPIC'])->nullable()->change();
        });
    }
};