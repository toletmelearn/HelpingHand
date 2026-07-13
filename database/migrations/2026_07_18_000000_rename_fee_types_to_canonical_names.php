<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\FeeType;

return new class extends Migration
{
    /**
     * Renames the originally-seeded FeeType names to the exact short names
     * requested for the fee-head master. Idempotent: a where()->update()
     * with no matching row is simply a no-op, so this is safe to run more
     * than once (e.g. after a fresh migrate on a DB that was seeded with
     * the new names directly).
     */
    private const RENAMES = [
        'Tuition Fee' => 'Tuition',
        'Admission Fee' => 'Admission',
        'Computer Fee' => 'Computer/IT',
        'Smart Class Fee' => 'Smart Class',
        'Exam Fee' => 'Exam',
        'Library Fee' => 'Library',
        'Sports Fee' => 'Sports',
        'Lab Fee' => 'Science Lab',
        'Miscellaneous Fee' => 'Miscellaneous',
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $old => $new) {
            FeeType::where('name', $old)->update(['name' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $old => $new) {
            FeeType::where('name', $new)->update(['name' => $old]);
        }
    }
};
