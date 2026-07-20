<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Teacher;
use App\Models\TeacherLogin;
use App\Services\TeacherAcademicService;

echo "=== DEBUGGING TEACHER ID 3 ===\n";

$teacher = Teacher::find(3);
if (!$teacher) {
    echo "Teacher ID 3 not found!\n";
    exit;
}

echo "Teacher ID 3: {$teacher->name}\n";

// Check if there's a teacher login for this teacher
$teacherLogin = TeacherLogin::where('teacher_id', 3)->first();
if ($teacherLogin) {
    echo "Teacher Login found for ID 3: Login ID {$teacherLogin->id}\n";
    
    // Check their assignments
    $academicData = TeacherAcademicService::getTeacherAcademicData($teacher->id);
    $assignedClassNames = $academicData['grouped_by_class']->map(function($classData) {
        return $classData['class']->name;
    });
    $assignedSubjectNames = $academicData['flat_assignments']->pluck('subject_name')->unique();

    echo "Assigned Class Names: " . implode(', ', $assignedClassNames->toArray()) . "\n";
    echo "Assigned Subject Names: " . implode(', ', $assignedSubjectNames->toArray()) . "\n";
} else {
    echo "No teacher login found for ID 3\n";
}

// Check exams created by this teacher
$exams = \App\Models\Exam::where('created_by', 3)->get();
echo "\nExams created by teacher ID 3:\n";
foreach ($exams as $exam) {
    echo "Exam ID: {$exam->id}, Name: {$exam->name}, Class: {$exam->class_name}, Subject: {$exam->subject}\n";
}