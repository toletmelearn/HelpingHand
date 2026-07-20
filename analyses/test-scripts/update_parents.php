<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ParentModel;
use App\Models\Student;

echo "Updating parent records with mobile and admission numbers...\n";

// Get the parent records and update them with mobile and admission numbers from their linked students
$parents = ParentModel::all();

foreach ($parents as $parent) {
    if ($parent->student_id) {
        $student = Student::find($parent->student_id);
        if ($student) {
            $parent->update([
                'mobile' => $student->mobile,
                'admission_number' => $student->admission_no
            ]);
            echo "Updated parent {$parent->name} with mobile: {$student->mobile}, admission: {$student->admission_no}\n";
        }
    }
}

echo "Parent records updated!\n";