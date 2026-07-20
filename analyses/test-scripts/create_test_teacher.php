<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Teacher;
use App\Models\TeacherLogin;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Section;

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║           CREATING TEST TEACHER                          ║\n";
echo "║        Test Teacher - Class 3, 4, 5 Assignments          ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// Create or find test teacher
$teacher = Teacher::firstOrCreate(
    ['email' => 'test.teacher@helpinghand.com'],
    [
        'name' => 'Test Teacher',
        'mobile' => '9999999999',
        'date_of_birth' => '1990-01-01',
        'gender' => 'Female',
        'qualification' => 'M.Ed',
        'experience_years' => 5,
        'date_of_joining' => '2020-06-01',
        'designation' => 'Senior Teacher',
        'address' => 'Test Address',
        'status' => 'active'
    ]
);

echo "✅ Teacher created/located: {$teacher->name} (ID: {$teacher->id})\n";

// Create teacher login
$teacherLogin = TeacherLogin::firstOrCreate(
    ['teacher_id' => $teacher->id],
    [
        'username' => 'test.teacher',
        'password' => bcrypt('password123'),
        'status' => 'active'
    ]
);

echo "✅ Teacher login created: {$teacherLogin->username}\n";

// Find required classes
$class3 = SchoolClass::where('name', 'Class 3')->first();
$class4 = SchoolClass::where('name', 'Class 4')->first();
$class5 = SchoolClass::where('name', 'Class 5')->first();

if (!$class3 || !$class4 || !$class5) {
    echo "❌ Required classes not found. Please seed classes first.\n";
    exit(1);
}

echo "✅ Found classes: Class 3, Class 4, Class 5\n";

// Find required subjects
$english = Subject::where('name', 'English')->first();
$hindi = Subject::where('name', 'Hindi')->first();
$sst = Subject::where('name', 'Social Studies')->first();

if (!$english || !$hindi || !$sst) {
    echo "❌ Required subjects not found. Please seed subjects first.\n";
    exit(1);
}

echo "✅ Found subjects: English, Hindi, Social Studies\n";

// Clear existing assignments for this teacher
TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)->delete();
echo "✅ Cleared existing assignments\n";

// Create assignments as requested
$assignments = [
    // Class 3 → English (make class teacher)
    [
        'teacher_id' => $teacher->id,
        'class_id' => $class3->id,
        'section_id' => null,
        'subject_id' => $english->id,
        'is_class_teacher' => true,
        'is_primary_subject_teacher' => true,
        'academic_year' => '2025-2026'
    ],
    // Class 4 → Hindi
    [
        'teacher_id' => $teacher->id,
        'class_id' => $class4->id,
        'section_id' => null,
        'subject_id' => $hindi->id,
        'is_class_teacher' => false,
        'is_primary_subject_teacher' => true,
        'academic_year' => '2025-2026'
    ],
    // Class 5 → SST
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
    echo "✅ Assigned: {$assignment->schoolClass->name} → {$assignment->subject->name} (Class Teacher: {$classTeacher})\n";
}

echo "\n🎯 TEST TEACHER CREATED SUCCESSFULLY\n";
echo "══════════════════════════════════════════════════════════\n";
echo "Login credentials:\n";
echo "Username: test.teacher\n";
echo "Password: password123\n";
echo "Email: test.teacher@helpinghand.com\n\n";

echo "Assignments:\n";
echo "Class 3 → English (Class Teacher)\n";
echo "Class 4 → Hindi\n";
echo "Class 5 → Social Studies\n\n";

echo "✅ Ready for testing dashboard, My Classes, and marks upload\n";
