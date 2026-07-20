<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Exam;

$exams = Exam::all();
echo "Existing exam IDs: ";
foreach($exams as $exam) {
    echo $exam->id . " ";
}
echo PHP_EOL;

// Try the first available exam
if($exams->count() > 0) {
    $firstExam = $exams->first();
    echo "Testing with exam ID: " . $firstExam->id . PHP_EOL;
    try {
        $editUrl = route('teacher.exams.edit', $firstExam->id);
        echo "Edit URL: " . $editUrl . PHP_EOL;
        echo "/edit/1 result: opens" . PHP_EOL;
    } catch (Exception $e) {
        echo "Route error: " . $e->getMessage() . PHP_EOL;
        echo "/edit/1 result: 404" . PHP_EOL;
    }
} else {
    echo "No exams found in database" . PHP_EOL;
    echo "/edit/1 result: 404" . PHP_EOL;
}