<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * exam_invigilator_duties (used by ExamArrangementController and the
     * ExamInvigilatorDuty model) exists on this dev DB but has no migration
     * anywhere in the codebase -- the same class of gap exam_seating_
     * arrangements had before its own migration was reconstructed on
     * 2026_07_12. A fresh install (migrate:fresh) is missing this table
     * entirely and every invigilator-duty page crashes for every exam, not
     * just ones with duties assigned. Recreated from the live schema (SHOW
     * COLUMNS / SHOW INDEX), guarded so it's a no-op here.
     */
    public function up(): void
    {
        if (Schema::hasTable('exam_invigilator_duties')) {
            return;
        }

        Schema::create('exam_invigilator_duties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->string('room_number');
            $table->string('role')->default('Main Invigilator');
            $table->timestamps();

            // Matches the live unique index: a teacher can hold only one
            // invigilator-duty row per exam (they can only be in one room
            // at a time) -- see saveInvigilators() fix in ExamArrangementController.
            $table->unique(['exam_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_invigilator_duties');
    }
};
