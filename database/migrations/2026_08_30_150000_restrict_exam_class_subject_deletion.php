<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * exams.class_id/subject_id were ON DELETE CASCADE (added in
     * 2026_02_15_081807_add_class_id_and_subject_id_to_exams_table.php).
     * That let deleting a SchoolClass or Subject silently wipe every Exam
     * referencing it -- and, transitively, every Result/CBSEResult/
     * ExamPaper/admit-card/seating row those exams have -- entirely
     * bypassing ExamDependencyChecker, since that guard only runs when
     * Exam::destroy() is called directly, not when the delete originates
     * on the Subject/SchoolClass side of the relationship.
     *
     * Switching to RESTRICT makes that deletion fail with a DB-level FK
     * violation instead, surfacing the conflict (and forcing the caller to
     * reassign or remove the exams first) instead of silently destroying
     * recorded results.
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropForeign(['subject_id']);
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->foreign('class_id')->references('id')->on('school_classes')->onDelete('restrict');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropForeign(['subject_id']);
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->foreign('class_id')->references('id')->on('school_classes')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
        });
    }
};
