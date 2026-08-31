<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (class/section, subject) paper within a Datesheet.
     * subject_id is a real FK (confirmed decision -- deliberately stricter
     * than the legacy Exam.subject free string; the string is derived from
     * Subject::name only at publish time, for backward compatibility with
     * the existing Exam table).
     *
     * exam_id is nullable and populated only once DatesheetPublishService
     * creates/links the real Exam row -- this is the whole integration
     * point with the existing, unchanged Exam/Marks/Result/Grade/Admit
     * Card chain (see DatesheetPublishService).
     *
     * section_id_norm mirrors the exact pattern already proven on
     * timetable_slots (migration 2026_07_27_112642): a STORED generated
     * column collapsing NULL section_id to 0, because MySQL treats
     * multiple NULLs in a unique index as distinct rather than colliding --
     * without this, two "whole class" entries for the same class+subject
     * could double-book at the DB level with no constraint catching it.
     * Same reasoning as documented there, reused verbatim rather than
     * reinvented.
     */
    public function up(): void
    {
        Schema::create('datesheet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('datesheet_id')->constrained('datesheets')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes');
            $table->foreignId('section_id')->nullable()->constrained('sections');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            // Not in the user's original field list, but Exam.total_marks/
            // passing_marks are NOT NULL on the existing exams table --
            // DatesheetPublishService needs real values per entry rather
            // than a single hardcoded guess for every subject (a 50-mark
            // unit test and a 100-mark term exam can coexist in one
            // datesheet). Defaults match Admin\ExamController's own
            // sensible fallback pattern; both remain editable per entry.
            $table->unsignedSmallInteger('total_marks')->default(100);
            $table->unsignedSmallInteger('passing_marks')->default(33);
            $table->string('room')->nullable();
            $table->text('instructions')->nullable();
            $table->foreignId('exam_id')->nullable()->constrained('exams')->nullOnDelete();
            $table->timestamps();

            $table->unsignedBigInteger('section_id_norm')
                ->storedAs('COALESCE(section_id, 0)');

            // No two papers for the same class+section on the same date can
            // overlap in time at all -- enforced at the application layer
            // (DatesheetConflictChecker, exact time ranges aren't a single
            // equality check); this DB-level constraint instead catches the
            // simpler, unambiguous case: the same class+section can never
            // have the same subject scheduled twice within one datesheet.
            $table->unique(
                ['datesheet_id', 'school_class_id', 'section_id_norm', 'subject_id'],
                'datesheet_entries_class_section_subject_unique'
            );

            $table->index(['school_class_id', 'section_id_norm', 'exam_date'], 'datesheet_entries_class_section_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datesheet_entries');
    }
};
