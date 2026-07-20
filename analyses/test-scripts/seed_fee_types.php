<?php
require_once 'vendor/autoload.php';

use App\Models\FeeType;

// Create some common fee types if they don't exist
$feeTypes = [
    ['name' => 'Tuition Fee', 'description' => 'Monthly tuition fee', 'status' => 'active'],
    ['name' => 'Transport Fee', 'description' => 'Bus transport fee', 'status' => 'active'],
    ['name' => 'Admission Fee', 'description' => 'One time admission fee', 'status' => 'active'],
    ['name' => 'Exam Fee', 'description' => 'Term exam fee', 'status' => 'active'],
    ['name' => 'Library Fee', 'description' => 'Library maintenance fee', 'status' => 'active'],
];

foreach ($feeTypes as $feeTypeData) {
    if (!FeeType::where('name', $feeTypeData['name'])->exists()) {
        FeeType::create($feeTypeData);
        echo "Created fee type: " . $feeTypeData['name'] . "\n";
    } else {
        echo "Fee type already exists: " . $feeTypeData['name'] . "\n";
    }
}

echo "Test fee types setup completed.\n";