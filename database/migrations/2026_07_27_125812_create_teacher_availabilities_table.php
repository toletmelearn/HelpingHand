<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Timetable-module T2a. Absence of a row = available; a row only
     * exists to record a BLOCKED slot (is_available=false in practice --
     * the column defaults true only so a future "explicitly marked
     * available" use case isn't precluded by the schema).
     *
     * `day` (1-7) is redundant with bell_timing_id's own day_of_week
     * (every bell_timings row already belongs to one specific day,
     * confirmed repeatedly across T1a/T1b/T1c -- e.g. "Monday Period 1"
     * and "Tuesday Period 1" are two different bell_timings rows, not
     * one row shared across days). Kept as its own column because the
     * plan's schema names both columns and includes `day` in the unique
     * key; TeacherAvailability::create()/update() sets it automatically
     * from the chosen bell_timing (via BellTiming's own day_order
     * accessor) rather than trusting client input for it, so the two
     * can't drift out of sync.
     */
    public function up(): void
    {
        Schema::create('teacher_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->unsignedTinyInteger('day'); // 1=Monday .. 7=Sunday, matches BellTiming::day_order
            $table->foreignId('bell_timing_id')->constrained('bell_timings')->onDelete('cascade');
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['teacher_id', 'day', 'bell_timing_id'], 'teacher_availabilities_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_availabilities');
    }
};
