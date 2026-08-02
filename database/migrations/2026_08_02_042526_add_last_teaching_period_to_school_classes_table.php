<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T6 item 3: a class's teaching day can end earlier than the school's
     * full grid (e.g. a senior class ending at period 6 while others run
     * to period 8) -- stores an order_index CEILING (bell_timings.
     * order_index is per-day-relative, so this composes for free with a
     * naturally shorter day, e.g. Saturday, with no special-casing).
     * NULL (the default) means uncapped -- every existing class keeps its
     * full day with no backfill needed.
     */
    public function up(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->unsignedTinyInteger('last_teaching_period')->nullable()->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropColumn('last_teaching_period');
        });
    }
};
