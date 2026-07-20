<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Bootstrap the application
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Starting database fixes...\n";

// Update admission numbers for students that don't have them
$studentsWithoutAdmission = DB::table('students')->whereNull('admission_no')->get();
$updatedAdmissionCount = 0;

foreach ($studentsWithoutAdmission as $student) {
    $admissionNo = 'STU' . str_pad($student->id, 3, '0', STR_PAD_LEFT);
    DB::table('students')->where('id', $student->id)->update(['admission_no' => $admissionNo]);
    $updatedAdmissionCount++;
    echo "Updated student ID {$student->id} with admission number: {$admissionNo}\n";
}

echo "Admission numbers updated: {$updatedAdmissionCount}\n";

// Update mobile numbers where they are null but phone exists
$studentsWithPhoneNoMobile = DB::table('students')->whereNull('mobile')->whereNotNull('phone')->get();
$updatedMobileCount = 0;

foreach ($studentsWithPhoneNoMobile as $student) {
    DB::table('students')->where('id', $student->id)->update(['mobile' => $student->phone]);
    $updatedMobileCount++;
    echo "Updated student ID {$student->id} mobile number: {$student->phone}\n";
}

echo "Mobile numbers updated: {$updatedMobileCount}\n";

// Show summary of all students
$totalStudents = DB::table('students')->count();
$studentsWithMobile = DB::table('students')->whereNotNull('mobile')->count();
$studentsWithAdmission = DB::table('students')->whereNotNull('admission_no')->count();

echo "\n=== DATABASE SUMMARY ===\n";
echo "Total students: {$totalStudents}\n";
echo "Students with mobile numbers: {$studentsWithMobile}\n";
echo "Students with admission numbers: {$studentsWithAdmission}\n";

// Show some sample data
echo "\n=== SAMPLE STUDENT DATA ===\n";
$sampleStudents = DB::table('students')->select('id', 'name', 'mobile', 'admission_no')->limit(5)->get();
foreach ($sampleStudents as $student) {
    echo "ID: {$student->id}, Name: {$student->name}, Mobile: {$student->mobile}, Admission: {$student->admission_no}\n";
}

echo "\nDatabase fixes completed!\n";