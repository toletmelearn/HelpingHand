<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tables = ['students', 'results', 'attendances', 'fee_collections', 'book_issues', 'student_hostel_allocations'];
foreach ($tables as $table) {
    if (Schema::hasTable($table)) {
        echo "Table: $table\n";
        echo "Columns: " . implode(', ', Schema::getColumnListing($table)) . "\n\n";
    } else {
        echo "Table $table does not exist.\n\n";
    }
}
