<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\FeeType;

return new class extends Migration
{
    /**
     * Seeds the "Security Deposit" fee type. There is no FeeType creation UI
     * (only a "Fee Type Master" screen for editing defaults on existing
     * types) -- fee types are seeded, same as 2026_06_25_100000. Schools
     * that don't want to collect a deposit simply never add this fee type
     * to a class's fee structure (it's picked per-class/session like any
     * other fee head via the existing fee-structure builder).
     */
    public function up(): void
    {
        FeeType::firstOrCreate(
            ['name' => 'Security Deposit'],
            [
                'description' => 'Refundable caution money, held until the student leaves the school',
                'status' => 'active',
                'is_optional' => true,
                'category' => 'deposit',
                'default_frequency' => 'yearly',
                'default_charge_months' => ['Annual'],
            ]
        );
    }

    public function down(): void
    {
        // No down action needed for data seeding
    }
};
