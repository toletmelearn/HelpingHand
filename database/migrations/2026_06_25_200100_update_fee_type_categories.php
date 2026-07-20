<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\FeeType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $categories = [
            'recurring' => ['Tuition Fee', 'Smart Class Fee'],
            'transport' => ['Transport Fee'],
            'admission' => ['Admission Fee'],
            'examination' => ['Exam Fee'],
            'annual' => ['Annual Charges'],
            'optional' => ['Computer Fee', 'Library Fee', 'Sports Fee', 'Lab Fee', 'ID Card Fee', 'Miscellaneous Fee', 'Uniform Fee']
        ];

        foreach ($categories as $category => $names) {
            FeeType::whereIn('name', $names)->update(['category' => $category]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        FeeType::query()->update(['category' => 'recurring']);
    }
};
