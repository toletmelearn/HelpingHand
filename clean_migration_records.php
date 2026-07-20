<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

DB::statement('SET FOREIGN_KEY_CHECKS=0;');
$tables = [
    'student_fee_discounts',
    'fee_discounts',
    'fee_receipts',
    'fee_collection_items',
    'fee_collections',
    'student_fee_assignments',
    'fee_structure_items',
    'fee_structures',
    'fee_types',
    'fee_structure_details',
    'fee_heads',
    'student_fee_ledgers',
    'fee_refunds'
];
foreach ($tables as $table) {
    Schema::dropIfExists($table);
}
DB::statement('SET FOREIGN_KEY_CHECKS=1;');
echo "Tables dropped.\n";

// Delete migration records from `migrations` table
$deleted = DB::table('migrations')
    ->where('migration', 'like', '2026_02_12_%')
    ->orWhere('migration', 'like', '2026_02_13_%')
    ->orWhere('migration', 'like', '2026_02_14_%')
    ->orWhere('migration', 'like', '2026_02_15_%')
    ->orWhere('migration', 'like', '2026_02_16_%')
    ->orWhere('migration', 'like', '2026_02_18_%')
    ->orWhere('migration', 'like', '2026_02_19_%')
    ->orWhere('migration', 'like', '2026_06_%')
    ->delete();

echo "Deleted $deleted migration records.\n";

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "Opcache reset.\n";
}
