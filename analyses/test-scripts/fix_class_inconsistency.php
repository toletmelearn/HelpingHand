<?php
require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

// Set the Laravel application context
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== FIXING CLASS DATA INCONSISTENCY ===\n\n";

// Get all students where class field doesn't match class_id relationship
$inconsistentStudents = DB::select("
    SELECT s.id, s.name, s.class as class_field, s.class_id, sc.name as school_class_name
    FROM students s
    INNER JOIN school_classes sc ON s.class_id = sc.id
    WHERE s.class != sc.name
");

echo "Found " . count($inconsistentStudents) . " students with inconsistent class data:\n";

$fixedCount = 0;
foreach ($inconsistentStudents as $student) {
    // Update the class field to match the school_class name
    DB::table('students')->where('id', $student->id)->update(['class' => $student->school_class_name]);
    echo "Fixed: Student {$student->id} ({$student->name}) - class field updated from '{$student->class_field}' to '{$student->school_class_name}'\n";
    $fixedCount++;
    
    // Limit to first 10 for this test run
    if ($fixedCount >= 10) {
        echo "... (showing first 10 fixes)\n";
        break;
    }
}

echo "\nUpdating ALL inconsistent records...\n";

// Now fix all inconsistent records
$allInconsistent = DB::select("
    SELECT s.id, s.name, s.class as class_field, s.class_id, sc.name as school_class_name
    FROM students s
    INNER JOIN school_classes sc ON s.class_id = sc.id
    WHERE s.class != sc.name
");

$actualFixedCount = 0;
foreach ($allInconsistent as $student) {
    DB::table('students')->where('id', $student->id)->update(['class' => $student->school_class_name]);
    $actualFixedCount++;
}

echo "Total records fixed: {$actualFixedCount}\n";

// Verify the specific student we were concerned about
$student281 = DB::select("SELECT id, name, class, class_id FROM students WHERE id = 281")[0];
$schoolClass281 = DB::select("SELECT name FROM school_classes WHERE id = ?", [$student281->class_id])[0];

echo "\nVerification for Student 281 (Demo Student 811):\n";
echo "  Student class field: '{$student281->class}'\n";
echo "  School class name: '{$schoolClass281->name}'\n";
echo "  Consistent: " . ($student281->class == $schoolClass281->name ? 'YES' : 'NO') . "\n";

echo "\n=== CLASS DATA FIX COMPLETE ===\n";