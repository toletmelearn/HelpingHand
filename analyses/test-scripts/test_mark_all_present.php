<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test the mark all present functionality
$date = now()->toDateString();
echo "Testing Mark All Present for date: $date\n";

// Check current attendance records
$currentRecords = DB::table('teacher_attendances')->whereDate('date', $date)->count();
echo "Current attendance records for today: $currentRecords\n";

// Get all teachers
$teachers = DB::table('teachers')->count();
echo "Total teachers: $teachers\n";

// Check if it's Sunday
$isSunday = date('w', strtotime($date)) == 0;
echo "Is Sunday: " . ($isSunday ? 'Yes' : 'No') . "\n";

if ($isSunday) {
    echo "Cannot mark all present on Sunday\n";
    exit;
}

// Simulate the mark all present logic
$attendances = [];
$timestamp = now();

$teacherRecords = DB::table('teachers')->get();

foreach ($teacherRecords as $teacher) {
    // Check if attendance already exists
    $existing = DB::table('teacher_attendances')
        ->where('teacher_id', $teacher->id)
        ->whereDate('date', $date)
        ->first();
    
    if ($existing) {
        // Update existing record
        DB::table('teacher_attendances')
            ->where('id', $existing->id)
            ->update([
                'status' => 'present',
                'remarks' => 'Auto-marked as present',
                'updated_by' => 1, // Assuming admin user ID is 1
                'updated_at' => $timestamp
            ]);
        echo "Updated attendance for teacher ID: $teacher->id\n";
    } else {
        // Create new record
        $attendances[] = [
            'teacher_id' => $teacher->id,
            'date' => $date,
            'status' => 'present',
            'remarks' => 'Auto-marked as present',
            'marked_by' => 1, // Assuming admin user ID is 1
            'updated_by' => 1,
            'created_at' => $timestamp,
            'updated_at' => $timestamp
        ];
        echo "Will create new attendance for teacher ID: $teacher->id\n";
    }
}

// Insert new records
if (!empty($attendances)) {
    DB::table('teacher_attendances')->insert($attendances);
    echo "Inserted " . count($attendances) . " new attendance records\n";
}

// Check final count
$finalRecords = DB::table('teacher_attendances')->whereDate('date', $date)->count();
echo "Final attendance records for today: $finalRecords\n";

echo "Test completed successfully!\n";