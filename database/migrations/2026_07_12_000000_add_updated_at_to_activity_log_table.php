<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The create_activity_log_table migration already defines `updated_at`,
     * but this dev DB's live `activity_log` table is missing it (confirmed
     * via SHOW COLUMNS -- only `created_at` exists), causing "Column not
     * found: 1054 Unknown column 'updated_at'" on any activity()->log()
     * call. Guarded so this is a safe no-op on any install whose table
     * already has the column (e.g. a fresh migrate on a new school's DB).
     */
    public function up(): void
    {
        if (Schema::hasTable('activity_log') && !Schema::hasColumn('activity_log', 'updated_at')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            });
        }
    }

    public function down(): void
    {
        // Intentionally not reversed -- dropping this column would put any
        // environment back into the broken state this migration fixes.
    }
};
