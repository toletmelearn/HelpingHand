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
        Schema::create('working_hours_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('exclude_lunch_break')->default(true);
            $table->boolean('exclude_other_breaks')->default(true);
            $table->string('calculation_method')->default('net_duration'); // net_duration, gross_duration
            $table->boolean('is_default')->default(false);
            $table->json('custom_breaks')->nullable(); // For additional break configurations
            $table->timestamps();
            
            // Indexes
            $table->index('is_default');
            $table->index('calculation_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('working_hours_configurations');
    }
};
