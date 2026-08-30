<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * exam_relieving_duties (used by ExamArrangementController::saveRelieving
     * and the ExamRelievingDuty model) exists on this dev DB with no
     * migration anywhere -- same gap as exam_invigilator_duties above.
     * Recreated from the live schema, guarded so it's a no-op here.
     *
     * Note: this is the flat schema the model/controller are actually built
     * against today (exam_id, teacher_id, time_slot, room_number -- one row
     * = one teacher's relief duty during one exam's time slot). It does NOT
     * add the richer (class_id, relieving_teacher_id) shape from the action
     * plan, which would encode "teacher A's class gets covered by teacher
     * B" -- that's a real schema/semantics change requiring a rewrite of
     * ExamRelievingDuty's fillable and every read/write in
     * ExamArrangementController, not just new columns. Deferred as a
     * separate, explicitly-scoped change (see Recommendations).
     */
    public function up(): void
    {
        if (Schema::hasTable('exam_relieving_duties')) {
            return;
        }

        Schema::create('exam_relieving_duties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->string('time_slot');
            $table->string('room_number');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_relieving_duties');
    }
};
