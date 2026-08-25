<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exams V1: the 2026_02_19_080345 rebuild of this table (dropIfExists +
     * recreate) never carried over the `metadata` json column the original
     * 2026_01_21_093000 migration had -- but ExamPaper's own $fillable/
     * $casts and Teacher\TeacherExamPaperController::store()'s "FK-SAFE
     * OWNERSHIP STRATEGY" (created_by left null on purpose; the acting
     * teacher's id/name stored in metadata instead, specifically to avoid
     * an FK constraint on created_by) both still depend on it existing.
     * Every real teacher submission has been throwing a raw SQL error
     * ever since.
     */
    public function up(): void
    {
        Schema::table('exam_papers', function (Blueprint $table) {
            $table->json('metadata')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_papers', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
