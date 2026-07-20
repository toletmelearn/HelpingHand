<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained('school_classes')->onDelete('cascade');
            $table->foreignId('section_id')->nullable()->constrained('sections')->onDelete('cascade');
            $table->foreignId('bell_timing_id')->constrained('bell_timings')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->string('room_number')->nullable();
            $table->string('academic_year')->nullable();
            $table->timestamps();

            // Indexing for faster scheduling searches and conflict checks
            $table->index(['school_class_id', 'section_id', 'bell_timing_id'], 'timetable_class_section_timing_idx');
            $table->index(['teacher_id', 'bell_timing_id'], 'timetable_teacher_timing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_slots');
    }
};
