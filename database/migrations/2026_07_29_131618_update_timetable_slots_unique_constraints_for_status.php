<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T4b item 1 (continued): the T1a/T2b unique indexes were built before
     * `status` existed, so a draft row proposing a different arrangement
     * for a class+period that already has a PUBLISHED slot would collide
     * with it as a false "double booking" -- the two rows really are
     * different bookings (one live, one proposed), and must be allowed to
     * coexist.
     *
     * Fix: two new STORED generated columns wrap the existing keys so
     * `status='archived'` rows are excluded from uniqueness entirely
     * (NULL, and MySQL/MariaDB's default NULL handling treats every NULL
     * as distinct -- the same trick T1a used for section_id_norm and T2b
     * used for teacher_bell_solo_key) -- archived rows are historical
     * snapshots from past PUBLISH events and are expected to repeat the
     * same class/period/teacher combination many times over. For any
     * non-archived row the column just passes the original key through
     * unchanged, and `status` is added to both composite indexes so a
     * 'draft' and a 'published' row at the identical class+period (or
     * teacher+period) no longer collide with EACH OTHER, while two
     * 'published' rows -- or two 'draft' rows -- still do, exactly as
     * before T4b.
     *
     * teacher_active_key is expressed directly from combined_class_group_id
     * + bell_timing_id + status (not by wrapping the existing
     * teacher_bell_solo_key column) to avoid relying on one generated
     * column referencing another, which is unnecessary risk given this
     * must also run on the test suite's SQLite connection.
     */
    public function up(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->unsignedBigInteger('class_bell_active_key')
                ->nullable()
                ->storedAs("CASE WHEN status = 'archived' THEN NULL ELSE bell_timing_id END")
                ->after('teacher_bell_solo_key');

            $table->unsignedBigInteger('teacher_active_key')
                ->nullable()
                ->storedAs("CASE WHEN status = 'archived' THEN NULL WHEN combined_class_group_id IS NULL THEN bell_timing_id ELSE NULL END")
                ->after('class_bell_active_key');
        });

        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropUnique('timetable_slots_class_section_bell_unique');
            $table->dropUnique('timetable_slots_teacher_bell_solo_unique');

            $table->unique(
                ['school_class_id', 'section_id_norm', 'class_bell_active_key', 'status'],
                'timetable_slots_class_section_bell_status_unique'
            );
            $table->unique(
                ['teacher_id', 'teacher_active_key', 'status'],
                'timetable_slots_teacher_bell_status_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropUnique('timetable_slots_class_section_bell_status_unique');
            $table->dropUnique('timetable_slots_teacher_bell_status_unique');

            $table->unique(
                ['school_class_id', 'section_id_norm', 'bell_timing_id'],
                'timetable_slots_class_section_bell_unique'
            );
            $table->unique(
                ['teacher_id', 'teacher_bell_solo_key'],
                'timetable_slots_teacher_bell_solo_unique'
            );
        });

        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropColumn(['class_bell_active_key', 'teacher_active_key']);
        });
    }
};
