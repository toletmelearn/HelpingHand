<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Timetable-module gap: a multi-day date-range holiday (e.g. a week-long
     * Diwali break) had no representation anywhere. SpecialDayOverride
     * (2026_01_26_025557) is a different, existing concept -- a single-date
     * flag (exam_day/half_day/event_day/emergency_closure) tied to a
     * specific bell_schedule_id, not a named, ranged academic-calendar
     * holiday. This table is additive, not a replacement.
     */
    public function up(): void
    {
        Schema::create('school_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year');
            $table->string('holiday_name');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('holiday_type', ['festival', 'leave', 'special', 'exam_break'])->default('leave');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('academic_year');
            $table->index(['start_date', 'end_date']);
            $table->unique(['academic_year', 'holiday_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_holidays');
    }
};
