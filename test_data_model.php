<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Test the data model
echo "=== DATA MODEL VERIFICATION ===\n\n";

// Check classes
echo "1. Classes in database:\n";
$classes = DB::table('school_classes')->orderBy('class_order')->get(['id', 'name', 'class_order']);
foreach ($classes as $class) {
    echo "  - ID: {$class->id}, Name: {$class->name}, Order: {$class->class_order}\n";
}

echo "\n2. Students with class_id:\n";
$students = DB::table('students')
    ->join('school_classes', 'students.class_id', '=', 'school_classes.id')
    ->select('students.name as student_name', 'school_classes.name as class_name', 'school_classes.class_order')
    ->limit(5)
    ->get();

foreach ($students as $student) {
    echo "  - {$student->student_name} -> {$student->class_name} (Order: {$student->class_order})\n";
}

echo "\n3. Class hierarchy verification:\n";
$class1 = DB::table('school_classes')->where('name', 'Class 1')->first();
$class2 = DB::table('school_classes')->where('name', 'Class 2')->first();

if ($class1 && $class2) {
    echo "  - Class 1 order: {$class1->class_order}\n";
    echo "  - Class 2 order: {$class2->class_order}\n";
    echo "  - Class 2 > Class 1: " . ($class2->class_order > $class1->class_order ? 'YES' : 'NO') . "\n";
}

echo "\n4. Students count by class:\n";
$studentCounts = DB::table('students')
    ->join('school_classes', 'students.class_id', '=', 'school_classes.id')
    ->select('school_classes.name', DB::raw('COUNT(*) as count'))
    ->groupBy('school_classes.name')
    ->orderBy('school_classes.class_order')
    ->get();

foreach ($studentCounts as $count) {
    echo "  - {$count->name}: {$count->count} students\n";
}

echo "\n=== DATA MODEL READY FOR STUDENT PROMOTION ===\n";