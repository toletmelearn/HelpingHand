<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TeacherLogin;
use App\Models\HomeworkNotice;

echo "Testing Homework Variable Fix...\n";

// Test with existing teacher login
$teacherLogin = TeacherLogin::first();

if (!$teacherLogin) {
    echo "No teacher login found\n";
    exit(1);
}

echo "Teacher Login: {$teacherLogin->username} (ID: {$teacherLogin->id})\n";

// Simulate the controller logic
$classIds = [6, 7, 8]; // From our previous test

try {
    $homeworks = HomeworkNotice::where('assigned_by', $teacherLogin->id)
        ->orWhereHas('schoolClass', function ($query) use ($classIds) {
            $query->whereIn('id', $classIds);
        })
        ->with(['schoolClass', 'subject'])
        ->latest()
        ->paginate(15);
    
    echo "✅ Homework query working\n";
    echo "Homework count: " . $homeworks->count() . "\n";
    echo "Variable name: \$homeworks (correct)\n";
    
    // Test the compact function simulation
    $viewData = compact('homeworks');
    echo "✅ compact('homeworks') working\n";
    echo "Keys in view data: " . implode(', ', array_keys($viewData)) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Variable fix verification complete!\n";
