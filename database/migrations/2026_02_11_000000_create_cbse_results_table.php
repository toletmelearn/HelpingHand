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
        // Create subjects table first (if not exists)
        if (!Schema::hasTable('subjects')) {
            Schema::create('subjects', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('subject_type')->default('scholastic'); // scholastic or co_scholastic
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // Drop existing results table if it exists and recreate with CBSE structure
        Schema::dropIfExists('cbse_results');
        
        Schema::create('cbse_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('exam_id');
            $table->unsignedBigInteger('subject_id');
            
            // CBSE Assessment Components
            $table->decimal('pt_marks', 5, 2)->default(0);        // Periodic Test Marks
            $table->decimal('notebook_marks', 5, 2)->default(0);   // Notebook Submission Marks
            $table->decimal('sea_marks', 5, 2)->default(0);        // Subject Enrichment Activity Marks
            $table->decimal('exam_marks', 5, 2)->default(0);       // Half Yearly/Annual Exam Marks
            
            // Calculated Fields
            $table->decimal('total_marks', 6, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->string('grade')->default('F');
            $table->string('result_status')->default('fail'); // pass or fail
            
            // Academic Information
            $table->string('academic_year', 20);
            $table->string('term', 20); // Term 1, Term 2, Annual
            
            // Additional Information
            $table->text('remarks')->nullable();
            $table->integer('class_rank')->nullable();
            $table->integer('section_rank')->nullable();
            
            // Locking System
            $table->boolean('is_locked')->default(false);
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            
            // Audit Trail
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->foreign('locked_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('generated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Unique Constraint to prevent duplicates
            $table->unique(['student_id', 'exam_id', 'subject_id'], 'unique_student_exam_subject');
            
            // Indexes for performance
            $table->index(['student_id', 'exam_id']);
            $table->index(['academic_year', 'term']);
            $table->index(['class_rank']);
            $table->index(['is_locked']);
        });
        
        // Create result_statistics table for performance analytics
        Schema::create('result_statistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_id');
            $table->string('academic_year', 20);
            $table->string('term', 20);
            $table->unsignedBigInteger('subject_id')->nullable();
            
            // Statistics
            $table->integer('total_students');
            $table->integer('passed_students');
            $table->integer('failed_students');
            $table->decimal('pass_percentage', 5, 2);
            $table->decimal('average_percentage', 5, 2);
            $table->decimal('highest_marks', 6, 2);
            $table->decimal('lowest_marks', 6, 2);
            
            $table->timestamps();
            
            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('result_statistics');
        Schema::dropIfExists('cbse_results');
        // Note: We don't drop subjects table as it might be used elsewhere
    }
};