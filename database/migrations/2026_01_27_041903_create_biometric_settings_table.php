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
        Schema::create('biometric_settings', function (Blueprint $table) {
            $table->id();
            $table->time('school_start_time')->default('08:00:00');
            $table->time('school_end_time')->default('16:00:00');
            $table->integer('grace_period_minutes')->default(15);
            $table->integer('lunch_break_duration')->default(60); // in minutes
            $table->integer('break_time_duration')->default(30); // in minutes
            $table->decimal('half_day_minimum_hours', 4, 2)->default(4.00);
            $table->integer('late_tolerance_limit')->default(30); // minutes after which considered late
            $table->integer('early_departure_tolerance')->default(30); // minutes before which considered early exit
            $table->boolean('exclude_lunch_from_working_hours')->default(true);
            $table->boolean('exclude_breaks_from_working_hours')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Ensure only one active setting
            $table->unique(['is_active'], 'unique_active_setting');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biometric_settings');
    }
};
