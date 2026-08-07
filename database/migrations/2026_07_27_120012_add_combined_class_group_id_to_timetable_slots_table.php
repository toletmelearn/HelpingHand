<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T2b item 3: a combined-class placement intentionally writes several
     * TimetableSlot rows (one per member class) that all share the same
     * teacher_id + bell_timing_id -- which the T1a
     * timetable_slots_teacher_bell_unique index (teacher_id,
     * bell_timing_id) would reject as a duplicate on the 2nd row, since
     * that index's whole job is "a teacher can't be in two places at
     * once". A combined group IS one teaching event though, so those
     * rows aren't a real double-booking.
     *
     * Fix: teacher_bell_solo_key is a STORED generated column that is
     * `bell_timing_id` for solo (non-grouped) rows and NULL for combined
     * rows -- expressed with CASE WHEN rather than IF(), since IF() is a
     * MySQL/MariaDB-only function and the test suite's sqlite connection
     * only validates a generated column's expression lazily, at INSERT
     * time, not at migration time: an IF()-based version passed
     * `migrate:fresh` silently and only failed the first time a test
     * actually wrote a timetable_slots row ("unknown function: IF()").
     * CASE WHEN is standard SQL and works on both engines. The new unique
     * index is on (teacher_id, teacher_bell_solo_key)
     * -- MySQL/MariaDB's *default* NULL handling (distinct, not
     * NULL-safe) is exactly what's wanted here, the mirror image of the
     * COALESCE trick T1a needed for section_id_norm: solo rows keep the
     * original per-teacher-per-period protection unchanged, while
     * combined rows (all NULL) never collide with each other at the DB
     * layer, however many member classes a group has.
     *
     * This DB index alone does not catch "teacher already has a solo
     * slot / a different combined group at this exact period" for a NEW
     * combined placement -- that's still a real conflict and is checked
     * at the application layer in TimetableController (unchanged
     * teacher_id scan across TimetableSlot, which combined rows are
     * still ordinary rows of, so it already sees them) before the write,
     * same layering as T1a (app-level check as primary guard, DB index as
     * the safety net under it, not a replacement for it).
     */
    public function up(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->foreignId('combined_class_group_id')
                ->nullable()
                ->after('teacher_id')
                ->constrained('combined_class_groups')
                ->nullOnDelete();
        });

        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->unsignedBigInteger('teacher_bell_solo_key')
                ->nullable()
                ->storedAs('CASE WHEN combined_class_group_id IS NULL THEN bell_timing_id ELSE NULL END')
                ->after('combined_class_group_id');
        });

        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropUnique('timetable_slots_teacher_bell_unique');
            $table->unique(
                ['teacher_id', 'teacher_bell_solo_key'],
                'timetable_slots_teacher_bell_solo_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropUnique('timetable_slots_teacher_bell_solo_unique');
            $table->unique(['teacher_id', 'bell_timing_id'], 'timetable_slots_teacher_bell_unique');
        });

        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropColumn('teacher_bell_solo_key');
        });

        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('combined_class_group_id');
        });
    }
};
