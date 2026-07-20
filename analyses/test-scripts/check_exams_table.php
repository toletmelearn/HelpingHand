<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== EXAMS TABLE COLUMNS ===\n";
$columns = DB::select('DESCRIBE exams');
foreach($columns as $col) {
    echo $col->Field . ' (' . $col->Type . ') ' . $col->Null . ' ' . ($col->Default ? 'DEFAULT '.$col->Default : 'NO DEFAULT') . ' ' . $col->Extra . "\n";
    if ($col->Field == 'exam_type') {
        echo "^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^\n";
        echo "FOUND exam_type column - checking its properties\n";
        echo "^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^\n";
    }
}