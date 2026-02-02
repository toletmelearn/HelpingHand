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
        Schema::create('lesson_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users');
            $table->foreignId('class_id')->constrained('school_classes');
            $table->foreignId('section_id')->constrained('sections');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->date('date');
            $table->string('topic');
            $table->text('learning_objectives');
            $table->text('teaching_method')->nullable();
            $table->text('homework_classwork');
            $table->text('books_notebooks_required');
            $table->text('submission_assessment_notes')->nullable();
            $table->enum('plan_type', ['daily', 'weekly', 'monthly']);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('modified_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_plans');
    }
};