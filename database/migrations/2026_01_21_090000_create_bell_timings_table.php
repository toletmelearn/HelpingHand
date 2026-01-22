<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bell_timings', function (Blueprint $table) {
            $table->id();
            $table->string('day_of_week');           // Monday, Tuesday, etc.
            $table->string('period_name');           // Period 1, Lunch, Break, etc.
            $table->time('start_time');              // Start time of period
            $table->time('end_time');                // End time of period
            $table->string('class_section')->nullable(); // Specific class/section
            $table->boolean('is_active')->default(true); // Whether schedule is active
            $table->boolean('is_break')->default(false); // Whether it's a break time
            $table->integer('order_index')->default(0);  // Order of periods in a day
            $table->string('academic_year')->nullable(); // Academic year identifier
            $table->string('semester')->nullable();      // Semester identifier
            $table->string('custom_label')->nullable();  // Custom label for special periods
            $table->string('color_code')->default('#007bff'); // Color for calendar representation
            $table->unsignedBigInteger('created_by')->nullable(); // User who created schedule
            
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes for performance
            $table->index(['day_of_week', 'is_active']);
            $table->index(['class_section', 'is_active']);
            $table->index(['academic_year', 'semester']);
            $table->index('order_index');
            $table->index(['day_of_week', 'class_section', 'order_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bell_timings');
    }
};