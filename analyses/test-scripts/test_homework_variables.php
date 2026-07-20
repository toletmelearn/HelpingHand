<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TeacherLogin;
use App\Models\TeacherClassSubjectAssignment;

echo "Testing Homework Variables Fix...\n";

// Test with existing teacher login
$teacherLogin = TeacherLogin::first();
$teacher = $teacherLogin->teacher;

if (!$teacherLogin) {
    echo "No teacher login found\n";
    exit(1);
}

echo "Teacher Login: {$teacherLogin->username} (ID: {$teacherLogin->id})\n";
echo "Teacher: {$teacher->name} (ID: {$teacher->id})\n";

// Simulate the controller logic
try {
    // Get homework data (like before)
    $classIds = TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
        ->pluck('class_id')
        ->unique();
    
    echo "✅ Class IDs retrieved: " . $classIds->count() . " classes\n";
    
    // Get assigned classes and subjects for the form
    $assignments = TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
        ->with(['schoolClass', 'subject'])
        ->get();
    
    $classes = $assignments->pluck('schoolClass')->unique('id')->values();
    $subjects = $assignments->pluck('subject')->unique('id')->values();
    
    echo "✅ Assignments retrieved: " . $assignments->count() . "\n";
    echo "✅ Classes for form: " . $classes->count() . "\n";
    echo "✅ Subjects for form: " . $subjects->count() . "\n";
    
    // Test the compact function simulation
    $viewData = compact('classes', 'subjects');
    echo "✅ compact('classes', 'subjects') working\n";
    echo "Keys in view data: " . implode(', ', array_keys($viewData)) . "\n";
    
    // Check if classes have the expected properties
    if ($classes->count() > 0) {
        $firstClass = $classes->first();
        echo "Sample class: {$firstClass->name} (ID: {$firstClass->id})\n";
    }
    
    if ($subjects->count() > 0) {
        $firstSubject = $subjects->first();
        echo "Sample subject: {$firstSubject->name} (ID: {$firstSubject->id})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ All variables fix verification complete!\n";
