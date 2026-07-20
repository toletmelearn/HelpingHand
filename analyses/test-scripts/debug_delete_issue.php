<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Exam;
use App\Models\Teacher;
use App\Models\TeacherLogin;
use App\Services\TeacherAcademicService;

echo "=== DEBUGGING DELETE ISSUE ===\n";

// Simulate what happens during delete
$teacherLogin = TeacherLogin::first();
if (!$teacherLogin) {
    echo "No teacher login found!\n";
    exit;
}

echo "Teacher Login ID: {$teacherLogin->id}\n";
echo "Teacher Login teacher_id: {$teacherLogin->teacher_id}\n";

$teacher = $teacherLogin->teacher;
if (!$teacher) {
    echo "No teacher found for this login!\n";
    exit;
}

echo "Actual Teacher ID: {$teacher->id}\n";
echo "Teacher Name: {$teacher->name}\n";

// Get the first exam to test with
$exam = Exam::first();
if (!$exam) {
    echo "No exams found!\n";
    exit;
}

echo "\nExam Details:\n";
echo "Exam ID: {$exam->id}\n";
echo "Exam Name: {$exam->name}\n";
echo "Exam created_by: {$exam->created_by}\n";
echo "Exam class_name: {$exam->class_name}\n";
echo "Exam subject: {$exam->subject}\n";

// Check if teacher created the exam
$isCreator = ($exam->created_by == $teacher->id);
echo "\nDid this teacher create the exam? " . ($isCreator ? "YES" : "NO") . "\n";

// Check teacher assignments
$academicData = TeacherAcademicService::getTeacherAcademicData($teacher->id);
$assignedClassNames = $academicData['grouped_by_class']->map(function($classData) {
    return $classData['class']->name;
});
$assignedSubjectNames = $academicData['flat_assignments']->pluck('subject_name')->unique();

echo "Assigned Class Names: " . implode(', ', $assignedClassNames->toArray()) . "\n";
echo "Assigned Subject Names: " . implode(', ', $assignedSubjectNames->toArray()) . "\n";

$hasClassAssignment = $assignedClassNames->contains($exam->class_name);
$hasSubjectAssignment = $assignedSubjectNames->contains($exam->subject);

echo "Has class assignment for this exam? " . ($hasClassAssignment ? "YES" : "NO") . "\n";
echo "Has subject assignment for this exam? " . ($hasSubjectAssignment ? "YES" : "NO") . "\n";

$canAccess = ($exam->created_by == $teacher->id) || 
           ($hasClassAssignment && $hasSubjectAssignment);

echo "Can access exam? " . ($canAccess ? "YES" : "NO") . "\n";