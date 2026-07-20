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
    echo "Dropped $table if existed\n";
}
DB::statement('SET FOREIGN_KEY_CHECKS=1;');
