<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T6 item 4 (team teaching): a slot can optionally carry a second
     * teacher, mirroring teacher_class_subject_assignments.co_teacher_id.
     * Deliberately kept as ONE row with two teacher columns, not two rows
     * (one per teacher) the way combined-class-groups writes one row per
     * member CLASS -- two rows here would collide with the existing
     * (school_class_id, section_id_norm, class_bell_active_key, status)
     * unique index, since both rows would share the identical class+period.
     *
     * New unique index (co_teacher_id, teacher_active_key, status) reuses
     * the SAME generated column T4b already built (NULL when archived, so
     * archived history rows never collide) -- catches a co-teacher being
     * double-booked as a co-teacher elsewhere. It does NOT catch a person
     * being the PRIMARY teacher in one row and co-teacher in another at
     * the same period -- that cross-column case is a known, documented gap
     * at the DB layer; GeneratorService::isHardLegal() is the actual
     * enforcer (checking both teacher_id and co_teacher_id together), the
     * same "solver is primary guard, DB constraint is partial backstop"
     * tradeoff already accepted for combined-groups' teacher_active_key
     * carve-out.
     */
    public function up(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->foreignId('co_teacher_id')->nullable()->after('teacher_id')
                ->constrained('teachers')->nullOnDelete();
        });

        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->unique(
                ['co_teacher_id', 'teacher_active_key', 'status'],
                'timetable_slots_co_teacher_bell_status_unique'
            );
        });
    }

    public function down(): void
    {
        // MariaDB refuses to drop the unique index first -- co_teacher_id's
        // foreign key relies on it to satisfy the "FK column must be
        // indexed" requirement (error 1553), so the FK has to go first.
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropForeign(['co_teacher_id']);
        });

        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropUnique('timetable_slots_co_teacher_bell_status_unique');
        });

        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropColumn('co_teacher_id');
        });
    }
};
