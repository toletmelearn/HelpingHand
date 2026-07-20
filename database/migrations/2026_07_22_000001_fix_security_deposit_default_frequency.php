<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\FeeType;

return new class extends Migration
{
    /**
     * The "Security Deposit" fee type (2026_07_15_000000) was seeded with
     * default_frequency = 'yearly', which bills it once per academic year --
     * not once for the student's whole enrollment as intended. Before this
     * fix, admins had no better option to pick from the Fee Type Master
     * screen than 'session_wise_admission' (which only bills genuinely-new
     * admissions -- a deposit added for an already-enrolled class would
     * never be billed to its continuing students). This sets it
     * unconditionally to the new 'one_time' option regardless of its
     * current value, since neither prior default was correct. This only
     * updates the FeeType default applied when an admin newly checks this
     * fee head in the structure builder; it does not touch any
     * already-created FeeStructureItem rows, so existing structures are
     * unaffected unless an admin re-saves them with the new "One-Time"
     * option.
     */
    public function up(): void
    {
        FeeType::where('name', 'Security Deposit')
            ->update([
                'default_frequency' => 'one_time',
                'default_charge_months' => json_encode(['OneTime']),
            ]);
    }

    public function down(): void
    {
        FeeType::where('name', 'Security Deposit')
            ->where('default_frequency', 'one_time')
            ->update([
                'default_frequency' => 'yearly',
                'default_charge_months' => json_encode(['Annual']),
            ]);
    }
};
