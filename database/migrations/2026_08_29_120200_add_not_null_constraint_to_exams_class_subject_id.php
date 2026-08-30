<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforces what the backfill migrations above made safe to enforce.
     * Refuses to run if any exam still has a NULL class_id/subject_id --
     * that means the backfill found unmapped rows (logged as warnings)
     * that still need manual resolution before this can be applied safely.
     * Resolve those by hand, then re-run migrate.
     *
     * Uses Blueprint::change() (doctrine/dbal) rather than a raw
     * `ALTER TABLE ... MODIFY` statement so this also runs against the
     * SQLite connection the test suite uses, not just MariaDB.
     */
    public function up(): void
    {
        $stillNull = DB::table('exams')
            ->whereNull('class_id')
            ->orWhereNull('subject_id')
            ->count();

        if ($stillNull > 0) {
            throw new \RuntimeException(
                "Cannot add NOT NULL constraint: {$stillNull} exam(s) still have a NULL class_id or subject_id. " .
                'Resolve them manually (see the Log::warning entries from the backfill migrations), then re-run migrate.'
            );
        }

        Schema::table('exams', function (Blueprint $table) {
            $table->unsignedBigInteger('class_id')->nullable(false)->change();
            $table->unsignedBigInteger('subject_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->unsignedBigInteger('class_id')->nullable()->change();
            $table->unsignedBigInteger('subject_id')->nullable()->change();
        });
    }
};
