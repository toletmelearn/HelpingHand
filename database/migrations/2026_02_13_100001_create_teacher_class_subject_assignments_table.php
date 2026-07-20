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
        Schema::create('teacher_class_subject_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->unsignedBigInteger('school_id')->nullable(); // Remove FK - schools table may not exist
            $table->foreignId('class_id')->constrained('school_classes')->onDelete('cascade');
            $table->foreignId('section_id')->nullable()->constrained('sections')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->boolean('is_class_teacher')->default(false);
            $table->string('academic_year')->nullable();
            $table->timestamps();
            
            // Prevent duplicate assignments
            $table->unique(['teacher_id', 'class_id', 'section_id', 'subject_id', 'academic_year'], 'teacher_class_subject_unique');
            $table->index('school_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_class_subject_assignments');
    }
};
