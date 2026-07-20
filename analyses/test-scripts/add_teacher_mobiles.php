<?php

/**
 * Script to add mobile numbers to teachers who don't have one
 * This is a one-time utility script to fix missing mobile numbers
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Teacher;

echo "🔄 Adding mobile numbers to teachers...\n\n";

$teachersWithoutMobile = Teacher::whereNull('mobile')
    ->orWhere('mobile', '')
    ->get();

if ($teachersWithoutMobile->isEmpty()) {
    echo "✅ All teachers already have mobile numbers!\n";
    exit(0);
}

$updated = 0;
$baseNumber = 9000000000; // Starting base number

foreach ($teachersWithoutMobile as $index => $teacher) {
    // Generate unique mobile number
    $mobileNumber = $baseNumber + $teacher->id;
    
    // Check if number already exists
    while (Teacher::where('mobile', $mobileNumber)->exists()) {
        $mobileNumber++;
    }
    
    $teacher->update(['mobile' => $mobileNumber]);
    $updated++;
    
    echo "✅ Updated Teacher #{$teacher->id} ({$teacher->name}): {$mobileNumber}\n";
}

echo "\n📊 Summary:\n";
echo "   ✅ Updated: {$updated} teachers with mobile numbers\n";
echo "\n🔑 You can now run: php artisan db:seed --class=AllTeachersLoginSeeder\n";
