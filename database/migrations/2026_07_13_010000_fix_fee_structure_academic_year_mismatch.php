<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * fee_structures.academic_year is free text; the current live rows say
     * "2026-27" while the real AcademicSession.code is "2025-2026" -- they
     * were never the same value. BulkFeeAssignmentService resolves the
     * session by matching this string, and when it can't, it silently falls
     * back to skipping the new-vs-continuing admission-fee dedup. This
     * re-points any FeeStructure whose academic_year doesn't match a real
     * session code onto the current session's code, so that protection
     * actually engages. Safe/idempotent: only touches rows that don't
     * already match a real session, and only when exactly one session is
     * marked current (if none or multiple are, there's no safe single
     * target to pick, so it does nothing rather than guess).
     */
    public function up(): void
    {
        if (!Schema::hasTable('fee_structures') || !Schema::hasTable('academic_sessions')) {
            return;
        }

        $validCodes = DB::table('academic_sessions')->pluck('code')->filter()->all();
        $currentSessions = DB::table('academic_sessions')->where('is_current', true)->get();

        if ($currentSessions->count() !== 1) {
            return;
        }

        $currentCode = $currentSessions->first()->code;
        if (empty($currentCode)) {
            return;
        }

        DB::table('fee_structures')
            ->whereNotIn('academic_year', $validCodes)
            ->update(['academic_year' => $currentCode]);
    }

    public function down(): void
    {
        // Not reversible -- the original values were already wrong (didn't
        // match any real session), there's nothing correct to restore to.
    }
};
