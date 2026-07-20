<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\FeeType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $feeTypes = [
            ['name' => 'Tuition Fee', 'description' => 'Monthly tuition fee', 'status' => 'active'],
            ['name' => 'Transport Fee', 'description' => 'Bus transport fee', 'status' => 'active'],
            ['name' => 'Admission Fee', 'description' => 'One time admission fee', 'status' => 'active'],
            ['name' => 'Computer Fee', 'description' => 'Computer lab fee', 'status' => 'active'],
            ['name' => 'Smart Class Fee', 'description' => 'Smart classroom fee', 'status' => 'active'],
            ['name' => 'Exam Fee', 'description' => 'Term exam fee', 'status' => 'active'],
            ['name' => 'Annual Charges', 'description' => 'Annual administration charges', 'status' => 'active'],
            ['name' => 'Library Fee', 'description' => 'Library maintenance fee', 'status' => 'active'],
            ['name' => 'Sports Fee', 'description' => 'Sports and games fee', 'status' => 'active'],
            ['name' => 'Lab Fee', 'description' => 'Science lab equipment fee', 'status' => 'active'],
            ['name' => 'ID Card Fee', 'description' => 'Student ID card printing fee', 'status' => 'active'],
            ['name' => 'Miscellaneous Fee', 'description' => 'Miscellaneous extra-curricular fee', 'status' => 'active'],
            ['name' => 'Uniform Fee', 'description' => 'School uniform charges', 'status' => 'active'],
        ];

        foreach ($feeTypes as $feeType) {
            FeeType::firstOrCreate(
                ['name' => $feeType['name']],
                [
                    'description' => $feeType['description'],
                    'status' => $feeType['status']
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down action needed for data seeding
    }
};
