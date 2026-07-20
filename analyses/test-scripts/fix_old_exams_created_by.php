<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Exam;
use App\Models\Teacher;

echo "Fixing old exams with null created_by values...\n";

// Get exams with null created_by
$examsWithNullCreatedBy = Exam::whereNull('created_by')->get();

echo "Found {$examsWithNullCreatedBy->count()} exams with null created_by\n";

foreach($examsWithNullCreatedBy as $exam) {
    // Try to determine the creator by examining who might have created it
    // Since we don't have explicit teacher tracking for these old records,
    // we'll assign a default teacher (the first teacher found)
    $firstTeacher = Teacher::first();
    
    if($firstTeacher) {
        $exam->update(['created_by' => $firstTeacher->id]);
        echo "Updated exam {$exam->id} with created_by = {$firstTeacher->id}\n";
    } else {
        echo "No teachers found to assign to exam {$exam->id}\n";
    }
}

echo "Done fixing old exams.\n";

// Verify the changes
$nullCount = Exam::whereNull('created_by')->count();
echo "Remaining exams with null created_by: {$nullCount}\n";