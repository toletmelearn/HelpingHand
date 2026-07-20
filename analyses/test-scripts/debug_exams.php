<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Exam;

try {
    echo "Testing exam retrieval...\n";
    
    // Try to get exams
    $exams = Exam::with(['createdBy'])->paginate(10);
    echo "Successfully retrieved " . $exams->count() . " exams\n";
    
    foreach ($exams as $exam) {
        echo "Exam ID: " . $exam->id . " | Name: " . $exam->name . " | Total Marks: " . $exam->total_marks . " (type: " . gettype($exam->total_marks) . ")\n";
    }
    
    echo "Testing exam creation...\n";
    // Try to create a test exam
    $testExam = Exam::create([
        'name' => 'Test Exam',
        'exam_type' => 'unit_test',
        'class_name' => '10A',
        'subject' => 'Mathematics',
        'exam_date' => '2026-02-10',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'total_marks' => 100,
        'passing_marks' => 33,
        'description' => 'Test exam for debugging',
        'academic_year' => '2025-26',
        'term' => 'Mid Term',
        'status' => 'scheduled',
        'created_by' => 1
    ]);
    
    echo "Successfully created test exam with ID: " . $testExam->id . "\n";
    echo "Test exam total_marks: " . $testExam->total_marks . " (type: " . gettype($testExam->total_marks) . ")\n";
    
    // Clean up
    $testExam->delete();
    echo "Test exam deleted\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}