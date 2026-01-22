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
        Schema::create('class_management', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'Class 1', 'Class 2', etc.
            $table->string('section')->nullable(); // e.g., 'A', 'B', 'C'
            $table->string('stream')->nullable(); // e.g., 'Science', 'Commerce', 'Arts'
            $table->integer('capacity')->default(40);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['name', 'section']); // Prevent duplicate class-section combinations
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_management');
    }
};
