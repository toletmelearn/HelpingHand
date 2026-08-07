<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T4b item 1: `status` defaults to 'published' so every existing row
     * backfills as published with no separate data-migration step -- they
     * were the live timetable before this column existed, and remain so.
     * `timetable_generation_id` ties a draft row back to the
     * GenerateTimetableJob run that produced it (nullable: published/
     * archived rows produced by the manual editor, or predating T4b
     * entirely, have none).
     */
    public function up(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->string('status')->default('published')->after('academic_year');
        });

        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->foreignId('timetable_generation_id')
                ->nullable()
                ->after('status')
                ->constrained('timetable_generations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('timetable_generation_id');
        });

        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
