<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TeacherLogin;
use App\Models\HomeworkNotice;
use Illuminate\Support\Facades\Schema;

echo "=== HOMEWORK SAVE DEBUGGING ===\n\n";

// Step 1: Check table structure
echo "📋 STEP 1: Checking homework_notices table structure\n";
echo "────────────────────────────────────────────────────\n";

try {
    $columns = Schema::getColumnListing('homework_notices');
    echo "✅ Table exists with columns:\n";
    foreach ($columns as $column) {
        echo "  - {$column}\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// Step 2: Check model fillable
echo "📋 STEP 2: Checking HomeworkNotice model fillable attributes\n";
echo "────────────────────────────────────────────────────────────\n";

$model = new HomeworkNotice();
$fillable = $model->getFillable();
echo "✅ Fillable attributes:\n";
foreach ($fillable as $attribute) {
    echo "  - {$attribute}\n";
}

echo "\n";

// Step 3: Check teacher login
echo "📋 STEP 3: Checking teacher authentication\n";
echo "────────────────────────────────────────────\n";

$teacherLogin = TeacherLogin::first();
if (!$teacherLogin) {
    echo "❌ No teacher login found\n";
    exit(1);
}

echo "✅ Teacher login found: {$teacherLogin->username} (ID: {$teacherLogin->id})\n";

// Step 4: Test creating a homework record
echo "\n📋 STEP 4: Testing homework creation\n";
echo "──────────────────────────────────────\n";

try {
    $testData = [
        'title' => 'Test Homework - Debug',
        'description' => 'This is a test homework for debugging purposes',
        'type' => 'homework',
        'class_id' => 6, // Class 3
        'subject_id' => 2, // English
        'assigned_by' => $teacherLogin->id,
        'due_date' => now()->addDays(7),
        'publish_date' => now(),
        'status' => 'active',
        'priority' => 'medium'
    ];
    
    echo "📝 Test data:\n";
    foreach ($testData as $key => $value) {
        echo "  {$key}: " . (is_object($value) ? $value->format('Y-m-d') : $value) . "\n";
    }
    
    echo "\n💾 Attempting to save...\n";
    $homework = HomeworkNotice::create($testData);
    
    echo "✅ Homework created successfully!\n";
    echo "📋 Homework ID: {$homework->id}\n";
    echo "📋 Title: {$homework->title}\n";
    echo "📋 Assigned by: {$homework->assigned_by}\n";
    
    // Verify it's in the database
    $found = HomeworkNotice::find($homework->id);
    if ($found) {
        echo "✅ Record verified in database\n";
    } else {
        echo "❌ Record not found in database\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error creating homework: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "File: " . $e->getFile() . "\n";
}

echo "\n";

// Step 5: Check route
echo "📋 STEP 5: Checking homework routes\n";
echo "────────────────────────────────────\n";

try {
    $routes = app('router')->getRoutes();
    $homeworkRoutes = [];
    
    foreach ($routes as $route) {
        if (strpos($route->getName(), 'teacher.homework') !== false) {
            $homeworkRoutes[] = [
                'name' => $route->getName(),
                'uri' => $route->uri(),
                'method' => implode('|', $route->methods())
            ];
        }
    }
    
    if (empty($homeworkRoutes)) {
        echo "❌ No teacher homework routes found\n";
    } else {
        echo "✅ Found homework routes:\n";
        foreach ($homeworkRoutes as $route) {
            echo "  {$route['method']} {$route['uri']} → {$route['name']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error checking routes: " . $e->getMessage() . "\n";
}

echo "\n🎯 DEBUGGING COMPLETE\n";
