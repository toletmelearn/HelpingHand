<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A reusable, day-independent Bell Timing structure an admin can define
     * once and apply to many classes. Deliberately NOT the bell_timings
     * table itself: bell_timings.class_section = NULL already carries a
     * real, load-bearing meaning ("applies to every class, school-wide" --
     * see FeasibilityService), and timetable_slots.bell_timing_id cascades
     * on delete, so a "template" can never safely be rows in that table --
     * editing/deleting a template must never risk live schedule data.
     */
    public function up(): void
    {
        Schema::create('bell_timing_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            // Optional hints only, shown pre-filled on the Apply form -- never
            // enforced. A template is not scoped to one academic year/semester.
            $table->string('academic_year')->nullable();
            $table->string('semester')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bell_timing_templates');
    }
};
