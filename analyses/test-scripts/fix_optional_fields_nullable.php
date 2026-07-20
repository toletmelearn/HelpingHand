<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "Making optional fields nullable...\n";
    
    // Make start_time and end_time nullable
    DB::statement("ALTER TABLE exams MODIFY start_time TIME NULL");
    echo "✓ start_time made nullable\n";
    
    DB::statement("ALTER TABLE exams MODIFY end_time TIME NULL");
    echo "✓ end_time made nullable\n";
    
    // Make academic_year nullable (since we might not always provide it)
    DB::statement("ALTER TABLE exams MODIFY academic_year VARCHAR(255) NULL");
    echo "✓ academic_year made nullable\n";
    
    // Make term nullable
    DB::statement("ALTER TABLE exams MODIFY term VARCHAR(255) NULL");
    echo "✓ term made nullable\n";
    
    echo "\nVerifying the changes...\n";
    $columns = DB::select('DESCRIBE exams');
    foreach($columns as $col) {
        if (in_array($col->Field, ['start_time', 'end_time', 'academic_year', 'term'])) {
            echo $col->Field . ': ' . $col->Null . ' ' . ($col->Default ? 'DEFAULT '.$col->Default : 'NO DEFAULT') . "\n";
        }
    }
    
    echo "\nDone! All optional fields are now nullable.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}