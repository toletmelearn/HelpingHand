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
        Schema::create('admin_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('module'); // e.g., 'biometric', 'fee', 'exam', 'attendance'
            $table->string('key'); // e.g., 'enable_biometric', 'auto_calculate_fees'
            $table->text('value')->nullable(); // JSON encoded for complex values
            $table->string('type')->default('boolean'); // boolean, string, integer, json
            $table->string('label'); // Human readable label
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['module', 'key']);
            $table->index('module');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_configurations');
    }
};
