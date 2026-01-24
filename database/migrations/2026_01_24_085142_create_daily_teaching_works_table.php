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
        Schema::create('daily_teaching_works', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('class_name');
            $table->string('section');
            $table->string('subject');
            $table->integer('period_number')->nullable();
            $table->unsignedBigInteger('teacher_id');
            $table->string('topic_covered');
            $table->text('teaching_summary')->nullable();
            $table->json('attachments')->nullable(); // Store file paths as JSON array
            $table->json('homework')->nullable(); // Store homework details as JSON
            $table->string('status')->default('published'); // draft, published, archived
            $table->string('academic_session')->nullable();
            $table->json('syllabus_mapping')->nullable(); // Map to syllabus units
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes for performance
            $table->index(['date', 'class_name', 'section', 'subject']);
            $table->index(['teacher_id', 'date']);
            $table->index(['class_name', 'subject', 'date']);
            $table->index(['status', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_teaching_works');
    }
};
