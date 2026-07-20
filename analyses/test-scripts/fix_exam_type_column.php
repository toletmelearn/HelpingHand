<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "Modifying exam_type column to add default value...\n";
    DB::statement("ALTER TABLE exams MODIFY exam_type VARCHAR(255) NOT NULL DEFAULT 'General'");
    echo "Success! Column modified.\n";
    
    echo "\nVerifying the change...\n";
    $columns = DB::select('DESCRIBE exams');
    foreach($columns as $col) {
        if ($col->Field == 'exam_type') {
            echo "exam_type column: " . $col->Field . ' (' . $col->Type . ') ' . $col->Null . ' ' . ($col->Default ? 'DEFAULT '.$col->Default : 'NO DEFAULT') . ' ' . $col->Extra . "\n";
            break;
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}