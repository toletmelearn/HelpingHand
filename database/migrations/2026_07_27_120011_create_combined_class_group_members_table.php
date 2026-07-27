<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T2b item 3: pivot from a combined-class group to the class-sections
     * that belong to it. section_id nullable = the whole class (matches
     * timetable_slots/teacher_class_subject_assignments' own null-means-
     * whole-class convention elsewhere in this module).
     */
    public function up(): void
    {
        Schema::create('combined_class_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combined_class_group_id')->constrained('combined_class_groups')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes');
            $table->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['combined_class_group_id', 'school_class_id', 'section_id'],
                'combined_group_member_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combined_class_group_members');
    }
};
