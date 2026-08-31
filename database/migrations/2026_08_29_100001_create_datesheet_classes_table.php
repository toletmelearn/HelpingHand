<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which classes/sections a Datesheet covers -- this is deliberately how
     * "scope" is expressed instead of a formal Wing entity (confirmed
     * decision: Wing stays out of scope; a curated class/section list
     * already achieves the same practical scheduling need).
     *
     * section_id nullable = whole class, matching the same convention
     * already used by TeacherClassSubjectAssignment/TimetableSlot/
     * CombinedClassGroupMember this session.
     */
    public function up(): void
    {
        Schema::create('datesheet_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('datesheet_id')->constrained('datesheets')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes');
            $table->foreignId('section_id')->nullable()->constrained('sections');
            $table->timestamps();

            $table->unique(['datesheet_id', 'school_class_id', 'section_id'], 'datesheet_classes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datesheet_classes');
    }
};
