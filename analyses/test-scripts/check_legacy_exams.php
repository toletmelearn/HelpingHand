<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Exam;
use App\Models\Teacher;

echo "Checking for legacy exams with invalid created_by values...\n";

// Get all exams with created_by values
$exams = Exam::whereNotNull('created_by')->get();

$validTeacherIds = Teacher::pluck('id')->toArray();
$invalidExams = [];

foreach($exams as $exam) {
    if (!in_array($exam->created_by, $validTeacherIds)) {
        $invalidExams[] = $exam;
        echo "Invalid exam ID: {$exam->id}, created_by: {$exam->created_by}\n";
    }
}

echo "Found " . count($invalidExams) . " exams with invalid created_by values\n";

if(count($invalidExams) > 0) {
    echo "These exams may have been created by admins or have legacy data.\n";
} else {
    echo "All exams have valid created_by values.\n";
}