<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T6 item 4 (team teaching only -- club/elective blocks and the manual
     * single-slot editor are deferred, per the approved REPORT-THEN-STOP):
     * an assignment can optionally carry a SECOND teacher who co-teaches
     * the identical class/subject/period alongside the primary teacher_id
     * (the real PDF's "CS -- GARISHT SINGH / Rajesh" cells). Nullable,
     * every existing row keeps its single-teacher behaviour with no
     * backfill needed. nullOnDelete (not cascade, unlike teacher_id) --
     * removing the co-teacher's Teacher record shouldn't destroy the
     * whole assignment, only drop the team-teaching pairing.
     */
    public function up(): void
    {
        Schema::table('teacher_class_subject_assignments', function (Blueprint $table) {
            $table->foreignId('co_teacher_id')->nullable()->after('teacher_id')
                ->constrained('teachers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_class_subject_assignments', function (Blueprint $table) {
            $table->dropForeign(['co_teacher_id']);
            $table->dropColumn('co_teacher_id');
        });
    }
};
