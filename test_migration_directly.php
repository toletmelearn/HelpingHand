<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $migration = include 'database/migrations/2026_02_12_081244_add_indexes_to_students_table_for_fee_search.php';
    echo "Running migration directly...\n";
    $migration->up();
    echo "Migration ran successfully!\n";
} catch (\Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
