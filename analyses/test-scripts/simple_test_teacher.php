<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Teacher;
use App\Models\TeacherLogin;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\SchoolClass;
use App\Models\Subject;

echo "Creating test teacher...\n";

// Find existing teacher or create simple one
$teacher = Teacher::where('email', 'test.teacher@helpinghand.com')->first();

if (!$teacher) {
    // Find any existing teacher to use
    $teacher = Teacher::first();
    if (!$teacher) {
        echo "No teachers found in database\n";
        exit(1);
    }
    echo "Using existing teacher: {$teacher->name}\n";
} else {
    echo "Found test teacher: {$teacher->name}\n";
}

// Create login if doesn't exist
$teacherLogin = TeacherLogin::firstOrCreate(
    ['teacher_id' => $teacher->id],
    [
        'username' => 'test.teacher',
        'password' => bcrypt('password123'),
        'status' => 'active'
    ]
);

echo "Login created: {$teacherLogin->username}\n";

// Find required classes
$class3 = SchoolClass::where('name', 'Class 3')->first();
$class4 = SchoolClass::where('name', 'Class 4')->first();
$class5 = SchoolClass::where('name', 'Class 5')->first();

if (!$class3 || !$class4 || !$class5) {
    echo "Required classes not found\n";
    exit(1);
}

echo "Found classes\n";

// Find required subjects
$english = Subject::where('name', 'English')->first();
$hindi = Subject::where('name', 'Hindi')->first();
$sst = Subject::where('name', 'Social Studies')->first();

if (!$english || !$hindi || !$sst) {
    echo "Required subjects not found\n";
    exit(1);
}

echo "Found subjects\n";

// Clear existing assignments for this teacher
TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)->delete();
echo "Cleared existing assignments\n";

// Create assignments
$assignments = [
    [
        'teacher_id' => $teacher->id,
        'class_id' => $class3->id,
        'section_id' => null,
        'subject_id' => $english->id,
        'is_class_teacher' => true,
        'is_primary_subject_teacher' => true,
        'academic_year' => '2025-2026'
    ],
    [
        'teacher_id' => $teacher->id,
        'class_id' => $class4->id,
        'section_id' => null,
        'subject_id' => $hindi->id,
        'is_class_teacher' => false,
        'is_primary_subject_teacher' => true,
        'academic_year' => '2025-2026'
    ],
    [
        'teacher_id' => $teacher->id,
        'class_id' => $class5->id,
        'section_id' => null,
        'subject_id' => $sst->id,
        'is_class_teacher' => false,
        'is_primary_subject_teacher' => true,
        'academic_year' => '2025-2026'
    ]
];

foreach ($assignments as $assignmentData) {
    $assignment = TeacherClassSubjectAssignment::create($assignmentData);
    $classTeacher = $assignmentData['is_class_teacher'] ? 'YES' : 'NO';
    echo "Assigned: {$assignment->schoolClass->name} → {$assignment->subject->name} (Class Teacher: {$classTeacher})\n";
}

echo "\n✅ Test setup complete!\n";
echo "Login: test.teacher / password123\n";
