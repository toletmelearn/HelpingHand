<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datesheet module: the planning/approval layer that sits in front of
     * Exam. One Datesheet is one examination event (e.g. "Term 1
     * Examinations 2026-27") covering many subjects/classes; publishing it
     * creates/links the real Exam rows the existing Marks/Result/Grade/
     * Admit Card chain already consumes unchanged (see DatesheetEntry.
     * exam_id and DatesheetPublishService).
     *
     * academic_session_id is a real FK (not a free string like
     * Exam.academic_year) -- deliberate, per confirmed decision: this
     * session's own audit found academic_year string values inconsistent
     * across exams/teacher_class_subject_assignments (e.g. "2025-2026" vs
     * "2026-2027" vs "2026-27" vs a leftover "-WALKTHROUGH-ARCHIVED"
     * suffix), so a new module shouldn't repeat that. The Exam row created
     * at publish time still gets a plain string academic_year, derived
     * from academicSession->name, to stay backward compatible with the
     * existing Exam schema.
     *
     * superseded_by_id: published Datesheets are immutable (confirmed
     * decision). A post-publish correction creates a brand-new Datesheet
     * that goes through the full draft->review->approve->publish cycle
     * again; this column links the old published version to its
     * replacement once that replacement itself publishes.
     */
    public function up(): void
    {
        Schema::create('datesheets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('exam_type');
            $table->foreignId('academic_session_id')->constrained('academic_sessions');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('draft');

            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('published_by')->nullable()->constrained('users');

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->foreignId('superseded_by_id')->nullable()->constrained('datesheets')->nullOnDelete();
            // The reverse pointer: set when a revision draft is created
            // (Admin\DatesheetController::revise()), so that
            // DatesheetPublishService can find and mark the ORIGINAL
            // published datesheet as superseded only once this revision
            // itself actually publishes -- an abandoned revision draft
            // must never affect the still-valid original.
            $table->foreignId('revises_id')->nullable()->constrained('datesheets')->nullOnDelete();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datesheets');
    }
};
