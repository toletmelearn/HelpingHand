<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'Mid Term Exam', 'Final Exam', 'Unit Test'
            $table->string('exam_type'); // e.g., 'unit_test', 'mid_term', 'final_exam'
            $table->string('class_name');
            $table->string('subject');
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('total_marks', 8, 2);
            $table->decimal('passing_marks', 8, 2);
            $table->text('description')->nullable();
            $table->string('academic_year');
            $table->string('term')->nullable();
            $table->enum('status', ['scheduled', 'ongoing', 'completed', 'cancelled'])->default('scheduled');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            
            $table->index(['class_name', 'exam_date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
