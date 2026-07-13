<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\FeeType;

return new class extends Migration
{
    /**
     * Net-new fee heads from the fee-head master spec that don't overlap
     * any originally-seeded name (see the sibling rename migration for
     * those). Same firstOrCreate seeding pattern as
     * 2026_06_25_100000_seed_default_fee_types / 2026_07_15_000000.
     */
    public function up(): void
    {
        $feeTypes = [
            ['name' => 'Registration', 'description' => 'One-time registration fee'],
            ['name' => 'Development Fund', 'description' => 'School infrastructure development fund'],
            ['name' => 'Robotics/STEM', 'description' => 'Robotics and STEM lab fee'],
            ['name' => 'Hostel', 'description' => 'Hostel accommodation fee'],
            ['name' => 'Mess', 'description' => 'Hostel mess/dining fee'],
            ['name' => 'Late Fine', 'description' => 'Late payment penalty'],
            ['name' => 'Activity', 'description' => 'Extra-curricular activity fee'],
        ];

        foreach ($feeTypes as $feeType) {
            FeeType::firstOrCreate(
                ['name' => $feeType['name']],
                ['description' => $feeType['description'], 'status' => 'active']
            );
        }
    }

    public function down(): void
    {
        // No down action needed for data seeding
    }
};
