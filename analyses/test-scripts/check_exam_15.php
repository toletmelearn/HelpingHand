<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Exam;

$exam = Exam::find(15);
echo "ID 15 exists: " . ($exam ? 'yes' : 'no') . PHP_EOL;

if ($exam) {
    echo "Exam name: " . $exam->name . PHP_EOL;
    echo "Created by: " . $exam->created_by . PHP_EOL;
    
    // Test route generation
    try {
        $editUrl = route('teacher.exams.edit', 15);
        echo "Edit URL: " . $editUrl . PHP_EOL;
        echo "/edit/1 result: opens" . PHP_EOL;
    } catch (Exception $e) {
        echo "Route error: " . $e->getMessage() . PHP_EOL;
        echo "/edit/1 result: 404" . PHP_EOL;
    }
} else {
    echo "/edit/1 result: 404" . PHP_EOL;
}