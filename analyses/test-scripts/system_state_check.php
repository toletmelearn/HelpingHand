<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CURRENT SYSTEM STATE ===\n\n";

// Check students table structure
echo "Students table columns:\n";
$columns = DB::select('DESCRIBE students');
foreach($columns as $column) {
    if (strpos($column->Field, 'class') !== false) {
        echo "  - {$column->Field}: {$column->Type} " . ($column->Null === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
}

echo "\nStudent class data:\n";
$studentsWithClass = DB::table('students')->whereNotNull('class')->count();
$studentsWithClassId = DB::table('students')->whereNotNull('class_id')->count();
$studentsWithSchoolClassId = DB::table('students')->whereNotNull('school_class_id')->count();

echo "  - Students with class (string): {$studentsWithClass}\n";
echo "  - Students with class_id: {$studentsWithClassId}\n";
echo "  - Students with school_class_id: {$studentsWithSchoolClassId}\n";

echo "\n=== ISSUE CONFIRMED ===\n";
echo "❌ school_class_id column missing from students table\n";
echo "❌ Data model is half-migrated and broken\n";
echo "❌ Need complete migration to school_class_id\n";

echo "\n=== REQUIRED ACTIONS ===\n";
echo "1. Create migration to add school_class_id column\n";
echo "2. Add foreign key constraint to school_classes.id\n";
echo "3. Backfill all students with correct school_class_id\n";
echo "4. Update all code to use school_class_id instead of class_id\n";