<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Exam;
use App\Models\Teacher;

echo "=== DEBUGGING ALL EXAMS ===\n";

$exams = Exam::all();
echo "Total exams: " . $exams->count() . "\n";

foreach ($exams as $exam) {
    $creator = Teacher::find($exam->created_by);
    echo "Exam ID: {$exam->id}, Name: {$exam->name}, Created by: ";
    if ($creator) {
        echo "{$creator->name} (ID: {$creator->id})";
    } else {
        echo "Unknown (ID: {$exam->created_by})";
    }
    echo ", Class: {$exam->class_name}, Subject: {$exam->subject}\n";
}

$teacherIds = $exams->pluck('created_by')->unique();
echo "\nUnique teacher IDs that created exams: " . implode(', ', $teacherIds->toArray()) . "\n";