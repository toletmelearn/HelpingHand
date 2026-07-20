<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== LESSON PLANS TABLE STRUCTURE ===\n";
$columns = DB::select('DESCRIBE lesson_plans');
foreach($columns as $col) {
    echo $col->Field . " | " . $col->Type . " | " . $col->Null . " | " . $col->Key . "\n";
}

echo "\n=== CHECKING REQUIRED COLUMNS ===\n";
$required = ['title', 'start_date', 'end_date', 'full_content', 'parent_visible_content', 'show_to_parents'];
foreach($required as $col) {
    $exists = DB::select('SHOW COLUMNS FROM lesson_plans LIKE ?', [$col]);
    echo $col . ': ' . (count($exists) > 0 ? 'EXISTS' : 'MISSING') . "\n";
}
