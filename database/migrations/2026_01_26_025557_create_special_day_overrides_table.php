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
        Schema::create('special_day_overrides', function (Blueprint $table) {
            $table->id();
            $table->date('override_date');
            $table->string('type', 50); // exam_day, half_day, event_day, emergency_closure
            $table->unsignedBigInteger('bell_schedule_id')->nullable(); // Reference to custom schedule
            $table->json('custom_periods')->nullable(); // Override periods if needed
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            
            $table->foreign('bell_schedule_id')->references('id')->on('bell_schedules')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->unique(['override_date'], 'unique_override_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('special_day_overrides');
    }
};
