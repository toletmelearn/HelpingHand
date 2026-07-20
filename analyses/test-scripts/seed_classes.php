<?php

use App\Models\SchoolClass;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$classes = [
    ['name' => 'Nursery', 'order' => 1],
    ['name' => 'LKG', 'order' => 2],
    ['name' => 'UKG', 'order' => 3],
    ['name' => 'Class 1', 'order' => 4],
    ['name' => 'Class 2', 'order' => 5],
    ['name' => 'Class 3', 'order' => 6],
    ['name' => 'Class 4', 'order' => 7],
    ['name' => 'Class 5', 'order' => 8],
    ['name' => 'Class 6', 'order' => 9],
    ['name' => 'Class 7', 'order' => 10],
    ['name' => 'Class 8', 'order' => 11],
    ['name' => 'Class 9', 'order' => 12],
    ['name' => 'Class 10', 'order' => 13],
    ['name' => 'Class 11 Science', 'order' => 14],
    ['name' => 'Class 11 Commerce', 'order' => 15],
    ['name' => 'Class 11 Arts', 'order' => 16],
    ['name' => 'Class 12 Science', 'order' => 17],
    ['name' => 'Class 12 Commerce', 'order' => 18],
    ['name' => 'Class 12 Arts', 'order' => 19],
];

echo "Seed SchoolClass table...\n";

foreach ($classes as $classData) {
    $class = SchoolClass::where('name', $classData['name'])->first();
    if (!$class) {
        SchoolClass::create([
            'name' => $classData['name'],
            'class_order' => $classData['order'],
            'is_active' => true,
            'description' => 'Regular ' . $classData['name'] . ' class'
        ]);
        echo "Created: " . $classData['name'] . "\n";
    } else {
        echo "Exists: " . $classData['name'] . "\n";
    }
}

echo "Done.\n";
