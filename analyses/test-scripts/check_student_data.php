<?php
require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use App\Models\Student;

// Set the Laravel application context
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CHECKING STUDENT DATA MISMATCH ===\n\n";

// Check the specific student
$student = Student::where('name', 'Demo Student 111')->first();

if ($student) {
    echo "Student found:\n";
    echo "  ID: " . $student->id . "\n";
    echo "  Name: " . $student->name . "\n";
    echo "  Mobile: " . $student->mobile . "\n";
    echo "  Phone: " . $student->phone . "\n";
} else {
    echo "Student 'Demo Student 111' not found.\n";
    // Let's check the first few students to see the pattern
    $students = Student::limit(5)->get();
    foreach ($students as $s) {
        echo "Student: " . $s->name . " | Mobile: " . $s->mobile . " | Phone: " . $s->phone . "\n";
    }
}

echo "\nChecking the list view query vs profile view data...\n";

// Check what the list view shows by querying for students with the specific mobile
$listStudent = DB::table('students')->where('mobile', '9847326902')->first();
if ($listStudent) {
    echo "Found student in DB with mobile 9847326902:\n";
    echo "  Name: " . $listStudent->name . "\n";
    echo "  Mobile: " . $listStudent->mobile . "\n";
    echo "  Phone: " . $listStudent->phone . "\n";
}

echo "\n=== CHECKING COMPLETE ===\n";