<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T6 item 2: per-generation choice of 'fixed_daily' (class-teacher-
     * style: the same pattern repeats every day) or 'rotating' (current
     * behaviour: each subject's periods spread across different days).
     * Defaults to 'rotating' so every pre-existing generation row keeps
     * its actual behaviour with no backfill needed.
     */
    public function up(): void
    {
        Schema::table('timetable_generations', function (Blueprint $table) {
            $table->string('style')->default('rotating')->after('school_class_ids');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_generations', function (Blueprint $table) {
            $table->dropColumn('style');
        });
    }
};
