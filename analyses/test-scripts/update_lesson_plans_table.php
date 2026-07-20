<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check existing columns
echo "=== EXISTING COLUMNS ===\n";
$existing = DB::select('DESCRIBE lesson_plans');
foreach($existing as $col) {
    echo $col->Field . "\n";
}

echo "\n=== ADDING MISSING COLUMNS ===\n";

// Add missing columns if they don't exist
$columnsToAdd = [
    'start_date' => 'DATE NULL',
    'end_date' => 'DATE NULL',
    'full_content' => 'TEXT NOT NULL',
    'parent_visible_content' => 'TEXT NULL',
    'show_to_parents' => 'TINYINT(1) NOT NULL DEFAULT 0'
];

foreach($columnsToAdd as $col => $definition) {
    $exists = DB::select("SHOW COLUMNS FROM lesson_plans LIKE '$col'");
    if(empty($exists)) {
        echo "Adding column: $col\n";
        DB::statement("ALTER TABLE lesson_plans ADD COLUMN $col $definition");
    } else {
        echo "Column already exists: $col\n";
    }
}

echo "\n=== FINAL STRUCTURE ===\n";
$final = DB::select('DESCRIBE lesson_plans');
foreach($final as $col) {
    echo $col->Field . " | " . $col->Type . " | " . $col->Null . "\n";
}
