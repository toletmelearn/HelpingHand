<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T3 item 1: link substitutions to the real timetable instead of a
     * free-typed 1-10 period_number with no relationship to bell_timings
     * or timetable_slots. Read-only check before writing this migration:
     * teacher_substitutions has 0 rows in the live DB, so there is no
     * data to map -- bell_timing_id can be added as a required column
     * directly. period_number/period_name are kept (existing views still
     * display them) but become optional going forward, no longer the
     * primary period identity.
     */
    public function up(): void
    {
        Schema::table('teacher_substitutions', function (Blueprint $table) {
            $table->foreignId('bell_timing_id')
                ->after('period_number')
                ->constrained('bell_timings');
        });

        Schema::table('teacher_substitutions', function (Blueprint $table) {
            $table->integer('period_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_substitutions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bell_timing_id');
        });

        Schema::table('teacher_substitutions', function (Blueprint $table) {
            $table->integer('period_number')->nullable(false)->change();
        });
    }
};
