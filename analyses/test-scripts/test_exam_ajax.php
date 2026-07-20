<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== EXAM AJAX DEBUG TEST ===\n\n";

// Test 1: Check if exams exist
echo "1. Checking if exams exist in database...\n";
$exams = \App\Models\Exam::take(5)->get(['id', 'name', 'subject', 'total_marks']);
echo "Found " . $exams->count() . " exams\n";

if ($exams->count() > 0) {
    echo "\nSample Exams:\n";
    foreach ($exams as $exam) {
        echo "  - ID: {$exam->id}, Name: {$exam->name}, Subject: {$exam->subject}, Marks: {$exam->total_marks}\n";
    }
    
    // Test 2: Test the getExamDetails method
    echo "\n2. Testing getExamDetails for first exam (ID: {$exams->first()->id})...\n";
    $testExam = \App\Models\Exam::find($exams->first()->id);
    if ($testExam) {
        $data = [
            'subject' => $testExam->subject,
            'total_marks' => $testExam->total_marks,
            'passing_marks' => $testExam->passing_marks,
            'name' => $testExam->name
        ];
        echo "Success! Data:\n";
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
    
    // Test 3: Check route
    echo "\n3. Checking if route is registered...\n";
    try {
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('admin.exams.details');
        if ($route) {
            echo "✓ Route 'admin.exams.details' exists\n";
            echo "  URI: " . $route->uri() . "\n";
        } else {
            echo "✗ Route 'admin.exams.details' NOT found\n";
            echo "  Trying alternative name 'exams.details'...\n";
            $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('exams.details');
            if ($route) {
                echo "✓ Route 'exams.details' exists\n";
                echo "  URI: " . $route->uri() . "\n";
            } else {
                echo "✗ Route 'exams.details' NOT found\n";
            }
        }
    } catch (Exception $e) {
        echo "Error checking route: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n⚠ No exams found in database. Please add some exams first.\n";
}

echo "\n=== TEST COMPLETE ===\n";
