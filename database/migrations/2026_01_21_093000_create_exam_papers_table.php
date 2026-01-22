<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_papers', function (Blueprint $table) {
            $table->id();
            $table->string('title');                           // Title of the exam paper
            $table->string('subject');                         // Subject name
            $table->string('class_section');                   // Class and section
            $table->string('exam_type');                       // Mid-term, Final, Unit Test, etc.
            $table->string('academic_year')->nullable();       // Academic year
            $table->string('semester')->nullable();            // Semester
            $table->string('paper_type');                      // Question Paper, Answer Key, etc.
            $table->string('file_path');                       // Path to uploaded file
            $table->string('file_name');                       // Original file name
            $table->bigInteger('file_size');                   // File size in bytes
            $table->string('file_extension');                  // File extension (pdf, doc, etc.)
            $table->unsignedBigInteger('uploaded_by')->nullable(); // User who uploaded
            $table->boolean('is_published')->default(false);   // Whether published
            $table->boolean('is_answer_key')->default(false);  // Whether it's an answer key
            $table->json('marks_distribution')->nullable();    // Marks distribution as JSON
            $table->integer('duration_minutes')->nullable();   // Exam duration in minutes
            $table->integer('total_marks')->nullable();        // Total marks
            $table->text('instructions')->nullable();          // Exam instructions
            $table->date('exam_date')->nullable();             // Date of exam
            $table->time('exam_time')->nullable();             // Time of exam
            $table->unsignedBigInteger('created_by')->nullable(); // User who created
            $table->unsignedBigInteger('approved_by')->nullable(); // User who approved
            $table->boolean('is_approved')->default(false);    // Whether approved
            $table->integer('download_count')->default(0);     // Download counter
            $table->string('access_level')->default('private'); // Access level
            $table->json('allowed_classes')->nullable();       // Classes allowed to access
            $table->boolean('password_protected')->default(false); // Password protection
            $table->string('access_password')->nullable();     // Access password
            $table->timestamp('valid_from')->nullable();       // Valid from date
            $table->timestamp('valid_until')->nullable();      // Valid until date
            $table->json('metadata')->nullable();              // Additional metadata
            
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes for performance
            $table->index(['subject', 'class_section']);
            $table->index(['exam_type', 'is_published']);
            $table->index(['academic_year', 'semester']);
            $table->index(['paper_type', 'is_answer_key']);
            $table->index(['exam_date', 'exam_time']);
            $table->index(['is_published', 'is_approved']);
            $table->index(['access_level', 'valid_until']);
            $table->fullText(['title', 'subject', 'class_section']); // For search
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_papers');
    }
};