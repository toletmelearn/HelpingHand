<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * attendances_temp (migration 2026_01_21_084000) and student_attendance
     * (migration 2026_02_18_120158_create_student_attendance_system) are both
     * fourth/duplicate attendance implementations with no model or controller
     * ever wired to them. See A4 of the Academic module rebuild.
     */
    public function up(): void
    {
        Schema::dropIfExists('attendances_temp');
        Schema::dropIfExists('student_attendance');
    }

    public function down(): void
    {
        Schema::create('attendances_temp', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->date('date');
            $table->string('status')->default('present');
            $table->timestamps();
        });

        Schema::create('student_attendance', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->date('date');
            $table->timestamp('check_in_time')->nullable();
            $table->timestamp('check_out_time')->nullable();
            $table->timestamps();
        });
    }
};
