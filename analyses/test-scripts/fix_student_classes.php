<?php

use App\Models\Student;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$studentCount = Student::count();
echo "Total Students: $studentCount\n";

if ($studentCount == 0) {
    echo "No students found.\n";
    exit;
}

$studentsWithClassId = Student::whereNotNull('class_id')->count();
echo "Students with class_id: $studentsWithClassId\n";

$studentsWithClassName = Student::whereNotNull('class')->count();
echo "Students with class name: $studentsWithClassName\n";

// Attempt to fix missing class_ids using class name
$classes = SchoolClass::all();
$updatedCount = 0;

foreach ($classes as $class) {
    // Find students with this class name but possibly wrong/missing ID
    // Note: This relies on 'class' column matching SchoolClass 'name'
    $affected = Student::where('class', $class->name)
        ->where(function($q) use ($class) {
            $q->whereNull('class_id')
              ->orWhere('class_id', '!=', $class->id);
        })
        ->update(['class_id' => $class->id]);
        
    if ($affected > 0) {
        echo "Updated $affected students to Class ID {$class->id} ({$class->name})\n";
        $updatedCount += $affected;
    }
}

echo "Total students updated: $updatedCount\n";
