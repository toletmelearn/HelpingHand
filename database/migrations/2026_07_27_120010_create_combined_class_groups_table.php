<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T2b item 3: a combined-class group is one teaching event (one
     * teacher, one subject, one period) that serves several classes at
     * once (e.g. a combined Sanskrit/Hindi group across two classes in
     * one room). Unlike bell_timings/timetable_slots' free-text
     * academic_year, the plan calls for a real FK here.
     */
    public function up(): void
    {
        Schema::create('combined_class_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('academic_session_id')->constrained('academic_sessions');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combined_class_groups');
    }
};
