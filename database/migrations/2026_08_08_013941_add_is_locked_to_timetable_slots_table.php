<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5 (Locked Lessons): a locked slot must never be moved by
     * Auto-Fix, a future Rebalance pass, or the generator when it builds a
     * new draft -- this flag is the single source of truth all three of
     * those consult. Defaults false so every existing row is unaffected.
     */
    public function up(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropColumn('is_locked');
        });
    }
};
