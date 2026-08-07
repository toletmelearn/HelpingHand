<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Timetable-module T1a: DB-level double-booking protection, under the
     * existing app-level checks in Admin\TimetableController, not a
     * replacement for them.
     *
     * timetable_slots has no separate `day` column -- bell_timing_id
     * already encodes a specific day (bell_timings.day_of_week) + period,
     * confirmed by the table's own existing (non-unique) indexes
     * timetable_class_section_timing_idx / timetable_teacher_timing_idx,
     * both already day-free. So the real composite keys are
     * (school_class_id, section_id, bell_timing_id) and
     * (teacher_id, bell_timing_id).
     *
     * section_id is nullable (class-wide slots); MySQL/MariaDB treats
     * multiple NULLs in a unique index as distinct, not equal, so a plain
     * index on the raw column would silently miss two class-wide slots
     * colliding on the same class+period. section_id_norm is a STORED
     * (not VIRTUAL) generated column -- COALESCE(section_id, 0) -- so the
     * index is genuinely NULL-safe; STORED chosen over VIRTUAL because
     * indexing VIRTUAL columns has had version/storage-engine-dependent
     * restrictions in MariaDB, and this must work on client XAMPP installs
     * we don't control (confirmed indexable either way on the dev
     * environment's MariaDB 10.4.32). Verified read-only first: no
     * `sections` row has id=0, so 0 can never collide with a genuine
     * section.
     *
     * teacher_id and bell_timing_id are both NOT NULL (confirmed via
     * SHOW COLUMNS against the live DB), so the teacher-side key needs no
     * equivalent normalization.
     *
     * Known, intentional gap (not fixable by this index shape, not in
     * scope for T1a): a class-wide slot (section_id_norm=0) and a
     * specific-section slot of the SAME class at the SAME period have
     * different section_id_norm values, so they will NOT collide against
     * each other here. That cross-case is not currently caught at the
     * app level either -- TimetableController::checkSlotConflicts() only
     * checks teacher and room overlap, never class/section overlap.
     * Flagged for T1b's conflict scan, not addressed here.
     *
     * Read-only check before writing this migration: timetable_slots has
     * 0 rows in the live DB -- zero duplicate groups on either key,
     * nothing to resolve.
     */
    public function up(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->unsignedBigInteger('section_id_norm')
                ->storedAs('COALESCE(section_id, 0)')
                ->after('section_id');
        });

        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->unique(
                ['school_class_id', 'section_id_norm', 'bell_timing_id'],
                'timetable_slots_class_section_bell_unique'
            );
            $table->unique(
                ['teacher_id', 'bell_timing_id'],
                'timetable_slots_teacher_bell_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropUnique('timetable_slots_class_section_bell_unique');
            $table->dropUnique('timetable_slots_teacher_bell_unique');
        });

        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropColumn('section_id_norm');
        });
    }
};
