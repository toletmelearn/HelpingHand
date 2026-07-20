<?php
require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

// Set the Laravel application context
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== SYNCHRONIZING STUDENT MOBILE AND PHONE FIELDS ===\n\n";

// Update all records so phone field matches mobile field
$updatedCount = DB::table('students')
    ->whereRaw('phone != mobile')  // Only update where they don't match
    ->update(['phone' => DB::raw('mobile')]);

echo "Updated {$updatedCount} student records to sync phone with mobile.\n";

// Verify the synchronization worked for our test student
$demoStudent = DB::table('students')->where('name', 'Demo Student 111')->first();
if ($demoStudent) {
    echo "\nVerification for Demo Student 111:\n";
    echo "  Mobile: {$demoStudent->mobile}\n";
    echo "  Phone: {$demoStudent->phone}\n";
    echo "  Match: " . ($demoStudent->mobile == $demoStudent->phone ? 'YES' : 'NO') . "\n";
}

// Check if there are any remaining inconsistencies
$inconsistencies = DB::table('students')
    ->select('id', 'name', 'mobile', 'phone')
    ->whereRaw('mobile != phone')
    ->limit(10)
    ->get();

if ($inconsistencies->count() > 0) {
    echo "\nRemaining inconsistencies found:\n";
    foreach ($inconsistencies as $student) {
        echo "  ID {$student->id}: {$student->name} - Mobile: {$student->mobile}, Phone: {$student->phone}\n";
    }
} else {
    echo "\n✅ All student records are now synchronized!\n";
}

echo "\n=== SYNC COMPLETE ===\n";