<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Exam;
use App\Models\Teacher;
use App\Models\TeacherLogin;

echo "=== DEBUGGING TEACHER EXAM PERMISSIONS ===\n";

// Get a teacher login (assuming there's at least one)
$teacherLogin = TeacherLogin::first();
if (!$teacherLogin) {
    echo "No teacher login found!\n";
    exit;
}

echo "Teacher Login ID: {$teacherLogin->id}\n";
echo "Teacher Login teacher_id: {$teacherLogin->teacher_id}\n";

// Get the actual teacher
$teacher = $teacherLogin->teacher;
if (!$teacher) {
    echo "No teacher found for this login!\n";
    var_dump($teacherLogin->teacher_id);
    exit;
}

echo "Actual Teacher ID: {$teacher->id}\n";

// Get exams created by this teacher
$exams = Exam::where('created_by', $teacher->id)->get();
echo "Number of exams created by this teacher: {$exams->count()}\n";

foreach ($exams as $exam) {
    echo "Exam ID: {$exam->id}, Name: {$exam->name}, created_by: {$exam->created_by}\n";
}

// Get some random exams to check
$allExams = Exam::limit(5)->get();
echo "\nSample of all exams:\n";
foreach ($allExams as $exam) {
    $creator = Teacher::find($exam->created_by);
    echo "Exam ID: {$exam->id}, Name: {$exam->name}, created_by: {$exam->created_by}";
    if ($creator) {
        echo ", Creator: {$creator->name}";
    } else {
        echo ", Creator: NOT FOUND (possibly invalid teacher ID)";
    }
    echo "\n";
}