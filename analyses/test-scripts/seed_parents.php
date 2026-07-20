<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ParentModel;
use App\Models\Student;

echo "Creating parent records...\n";

$students = Student::take(3)->get();

foreach ($students as $index => $student) {
    ParentModel::create([
        'name' => 'Parent of ' . $student->name,
        'email' => 'parent' . ($index + 1) . '@example.com',
        'phone' => '987654321' . ($index + 1),
        'password' => bcrypt('123456'),
        'student_id' => $student->id,
    ]);
    echo "Created parent for student: " . $student->name . "\n";
}

echo "Seeding complete!\n";