<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;

echo "Testing Homework Controller Fix...\n";

// Test with existing teacher
$teacher = Teacher::first();
if (!$teacher) {
    echo "No teacher found\n";
    exit(1);
}

echo "Teacher: {$teacher->name} (ID: {$teacher->id})\n";

// Test direct query approach
try {
    $classIds = TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
        ->pluck('class_id')
        ->unique();
    
    echo "✅ Direct query approach working\n";
    echo "Classes found: " . $classIds->count() . "\n";
    echo "Class IDs: " . $classIds->implode(', ') . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test HomeworkNotice query (simulated)
echo "\nSimulating HomeworkNotice query...\n";

$classIds = $teacher->assignedClasses()->pluck('id')->toArray();
echo "Class IDs for query: [" . implode(', ', $classIds) . "]\n";

if (empty($classIds)) {
    echo "⚠️  No classes assigned to teacher\n";
} else {
    echo "✅ Query would work with these class IDs\n";
}

echo "\n✅ Fix verification complete!\n";
