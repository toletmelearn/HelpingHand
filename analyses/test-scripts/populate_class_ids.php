<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;
use App\Models\Student;
use App\Models\SchoolClass;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== POPULATING STUDENT CLASS_ID VALUES ===\n\n";

$students = Student::whereNull('class_id')->get();
echo "Students needing class_id: " . $students->count() . "\n";

$updated = 0;
foreach($students as $student) {
    $class = SchoolClass::where('name', $student->class)->first();
    if($class) {
        $student->class_id = $class->id;
        $student->save();
        echo "Assigned {$student->name} to {$class->name}\n";
        $updated++;
    } else {
        echo "No class found for student {$student->name} with class '{$student->class}'\n";
    }
}

echo "\n=== CLASS_ID POPULATION COMPLETE ===\n";
echo "Updated {$updated} students\n";