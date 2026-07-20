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
    'fee_collections',
    'student_fee_assignments',
    'fee_structure_details',
    'fee_heads'
];
foreach ($tables as $table) {
    Schema::dropIfExists($table);
}
DB::statement('SET FOREIGN_KEY_CHECKS=1;');
echo "Cleaned tables.\n";

// Run artisan migrate
$exitCode = \Illuminate\Support\Facades\Artisan::call('migrate');
echo "Artisan migrate exit code: $exitCode\n";
echo \Illuminate\Support\Facades\Artisan::output();
