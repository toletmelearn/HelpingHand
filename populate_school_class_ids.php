<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;
use App\Models\Student;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== POPULATING STUDENT SCHOOL_CLASS_ID VALUES ===\n\n";

$students = Student::whereNull('school_class_id')->get();
echo "Students needing school_class_id: " . $students->count() . "\n";

$updated = 0;
foreach($students as $student) {
    $class = SchoolClass::where('name', $student->class)->first();
    if($class) {
        // Update without triggering events
        DB::table('students')->where('id', $student->id)->update(['school_class_id' => $class->id]);
        echo "Assigned {$student->name} to {$class->name}\n";
        $updated++;
    } else {
        echo "No class found for student {$student->name} with class '{$student->class}'\n";
    }
}

echo "\n=== SCHOOL_CLASS_ID POPULATION COMPLETE ===\n";
echo "Updated {$updated} students\n";