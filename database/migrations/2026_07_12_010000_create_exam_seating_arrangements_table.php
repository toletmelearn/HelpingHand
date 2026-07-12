<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The exam_seating_arrangements table (used by ExamArrangementController
     * and read directly from student/admit-cards/{pdf,show}.blade.php,
     * admin/admit-cards/{preview}.blade.php) exists on this dev DB but has
     * no migration anywhere in the codebase -- confirmed via
     * `grep -rl seating database/migrations/` returning nothing while the
     * table is live. A fresh install (migrate:fresh, or any other school's
     * new deployment) would be missing this table entirely, and every
     * admit-card view that queries it would crash for every student, not
     * just ones with a seat assigned. Recreated from the live schema
     * (SHOW COLUMNS / SHOW INDEX), guarded so it's a no-op here.
     */
    public function up(): void
    {
        if (Schema::hasTable('exam_seating_arrangements')) {
            return;
        }

        Schema::create('exam_seating_arrangements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->string('room_number');
            $table->string('seat_number');
            $table->timestamps();

            $table->unique(['exam_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_seating_arrangements');
    }
};
