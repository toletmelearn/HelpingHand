<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for fee_structures table.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'sqlite') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }
        Schema::dropIfExists('fee_structures');
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->string('class_name'); // Nursery, LKG, UKG, 1,2,3...12
            $table->string('academic_year'); // 2026-27
            $table->enum('frequency', ['monthly', 'quarterly', 'yearly', 'custom']);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['class_name', 'academic_year']);
            $table->index('status');
        });
        if ($driver !== 'sqlite') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_structures');
    }
};