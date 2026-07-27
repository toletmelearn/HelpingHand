<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Timetable-module T2a: adds to the EXISTING assignments table
     * (teacher_class_subject_assignments), per the plan's explicit
     * "do not create a new assignments table" instruction.
     */
    public function up(): void
    {
        Schema::table('teacher_class_subject_assignments', function (Blueprint $table) {
            $table->unsignedTinyInteger('periods_per_week')->nullable()->after('is_primary_subject_teacher');
            $table->boolean('require_consecutive')->default(false)->after('periods_per_week');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_class_subject_assignments', function (Blueprint $table) {
            $table->dropColumn(['periods_per_week', 'require_consecutive']);
        });
    }
};
