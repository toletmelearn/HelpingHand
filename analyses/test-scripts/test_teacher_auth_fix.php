<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TeacherLogin;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;

echo "Testing Teacher Auth Fix...\n";

// Test with existing teacher login
$teacherLogin = TeacherLogin::first();

if (!$teacherLogin) {
    echo "No teacher login found\n";
    exit(1);
}

echo "Teacher Login: {$teacherLogin->username} (ID: {$teacherLogin->id})\n";

// Test accessing teacher relationship
try {
    $teacher = $teacherLogin->teacher;
    echo "✅ Teacher relationship working\n";
    echo "Teacher: {$teacher->name} (ID: {$teacher->id})\n";
} catch (Exception $e) {
    echo "❌ Error accessing teacher: " . $e->getMessage() . "\n";
    exit(1);
}

// Test getting assigned classes
try {
    $classIds = TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
        ->pluck('class_id')
        ->unique();
    
    echo "✅ Class assignment query working\n";
    echo "Assigned classes: " . $classIds->count() . "\n";
    echo "Class IDs: " . $classIds->implode(', ') . "\n";
} catch (Exception $e) {
    echo "❌ Error getting class assignments: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ All tests passed! The fix should work.\n";
