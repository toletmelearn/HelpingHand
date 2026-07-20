<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Exam;
use App\Models\Teacher;
use App\Models\TeacherLogin;

echo "=== DEBUGGING TEACHER ID AND EXAM CREATOR RELATIONSHIP ===\n";

// Simulate teacher login
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
    exit;
}

echo "Actual Teacher ID: {$teacher->id}\n";
echo "Teacher Name: {$teacher->name}\n";

// Check some exams
$exams = Exam::limit(5)->get();
echo "\nExam creator verification:\n";
foreach ($exams as $exam) {
    $creator = Teacher::find($exam->created_by);
    $isCreator = ($exam->created_by == $teacher->id);
    
    echo "Exam ID: {$exam->id}, Name: {$exam->name}\n";
    echo "  Created by: " . ($creator ? $creator->name : "Unknown (ID: {$exam->created_by})") . "\n";
    echo "  Current teacher created this exam: " . ($isCreator ? "YES" : "NO") . "\n";
    echo "  created_by == teacher->id: " . ($exam->created_by == $teacher->id ? "TRUE" : "FALSE") . "\n";
    echo "\n";
}