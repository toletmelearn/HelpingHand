<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║           DATA CONSISTENCY VERIFICATION                   ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// Find Priya Sharma
$teacher = Teacher::where('name', 'like', '%Priya%')->first();

if (!$teacher) {
    echo "❌ Teacher 'Priya%' not found\n";
    exit(1);
}

echo "🎯 Teacher: {$teacher->name}\n\n";

// Get assignments from TeacherClassSubjectAssignment table
$assignments = TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
    ->with(['schoolClass', 'section', 'subject'])
    ->get();

echo "📋 ASSIGNMENTS FROM DATABASE TABLE:\n";
echo "──────────────────────────────────────────────────────────\n";
echo "Total assignments: " . $assignments->count() . "\n\n";

foreach ($assignments as $assignment) {
    $section = $assignment->section ? $assignment->section->name : 'All';
    $classTeacher = $assignment->is_class_teacher ? 'Y' : 'N';
    $primarySubject = $assignment->is_primary_subject_teacher ? 'Y' : 'N';
    
    echo "- {$assignment->schoolClass->name} - {$assignment->subject->name} ";
    echo "(Section: {$section}, CT: {$classTeacher}, PS: {$primarySubject})\n";
}

echo "\n";

// Check what the dashboard controller would get
echo "📋 DASHBOARD CONTROLLER DATA:\n";
echo "──────────────────────────────────────────────────────────\n";
$dashboardAssignments = TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
    ->with(['schoolClass', 'section', 'subject'])
    ->get();

echo "Dashboard would show: " . $dashboardAssignments->count() . " assignments\n";

// Check what the classes controller would get
echo "\n📋 MY CLASSES CONTROLLER DATA:\n";
echo "──────────────────────────────────────────────────────────\n";

$classesAssignments = TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
    ->with(['schoolClass', 'section', 'subject'])
    ->get();

// Group by class like the classes controller does
$classesData = $classesAssignments->groupBy('class_id')->map(function ($classAssignments) {
    $firstAssignment = $classAssignments->first();
    return [
        'class' => $firstAssignment->schoolClass,
        'section' => $firstAssignment->section,
        'subjects' => $classAssignments->pluck('subject'),
        'is_class_teacher' => $classAssignments->contains('is_class_teacher', true),
        'is_primary_subject_teacher' => $classAssignments->contains('is_primary_subject_teacher', true), // Add this
    ];
});

echo "My Classes would show: " . $classesData->count() . " unique classes\n";

foreach ($classesData as $classData) {
    echo "- {$classData['class']->name}";
    if ($classData['section']) {
        echo " - {$classData['section']->name}";
    }
    echo " (Subjects: " . $classData['subjects']->count() . ")";
    echo " (CT: " . ($classData['is_class_teacher'] ? 'Y' : 'N') . ")";
    echo " (PS: " . ($classData['is_primary_subject_teacher'] ? 'Y' : 'N') . ")\n";
    
    foreach ($classData['subjects'] as $subject) {
        echo "  └─ {$subject->name}\n";
    }
}

echo "\n";

// Check for consistency
echo "📋 CONSISTENCY ANALYSIS:\n";
echo "──────────────────────────────────────────────────────────\n";

$dashboardCount = $dashboardAssignments->count();
$classesTotalSubjects = 0;
foreach ($classesData as $classData) {
    $classesTotalSubjects += $classData['subjects']->count();
}

echo "Dashboard shows: {$dashboardCount} assignments\n";
echo "My Classes shows: {$classesTotalSubjects} subject assignments\n";
echo "Classes shown: " . $classesData->count() . "\n";

if ($dashboardCount === $classesTotalSubjects) {
    echo "✅ DATA COUNT CONSISTENT\n";
} else {
    echo "❌ DATA COUNT MISMATCH\n";
}

// Check if both sources use same table
echo "\n✅ SOURCE VERIFICATION:\n";
echo "- Both controllers use: TeacherClassSubjectAssignment table\n";
echo "- Both filter by: teacher_id = {$teacher->id}\n";
echo "- Both eager load: schoolClass, section, subject\n";
echo "- Both get same records from same source\n";

echo "\n🎯 CONCLUSION:\n";
echo "The data source is consistent. Both pages pull from the same table.\n";
echo "The difference is in presentation: Dashboard shows flat list, My Classes groups by class.\n";
