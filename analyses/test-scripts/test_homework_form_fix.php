<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TeacherLogin;
use App\Models\HomeworkNotice;
use Illuminate\Http\Request;

echo "=== HOMEWORK FORM FIX TESTING ===\n\n";

// Test the complete form submission process
$teacherLogin = TeacherLogin::first();
if (!$teacherLogin) {
    echo "❌ No teacher login found\n";
    exit(1);
}

echo "✅ Teacher login: {$teacherLogin->username} (ID: {$teacherLogin->id})\n";

// Simulate form data that would be submitted
$formData = [
    'title' => 'Chapter 5 Mathematics Homework',
    'description' => 'Complete exercises 1-10 from Chapter 5',
    'type' => 'homework',  // This was missing before
    'class_id' => 6,       // Class 3
    'subject_id' => 2,     // English
    'due_date' => date('Y-m-d', strtotime('+7 days')),
    'priority' => 'medium'  // This was missing before
];

echo "\n📝 Form data to be submitted:\n";
foreach ($formData as $key => $value) {
    echo "  {$key}: {$value}\n";
}

// Test validation
echo "\n📋 STEP 1: Testing validation\n";
echo "─────────────────────────────\n";

try {
    $request = Request::create('/teacher/homework', 'POST', $formData);
    
    $rules = [
        'title' => 'required|string|max:255',
        'description' => 'required|string',
        'type' => 'required|in:homework,notice,announcement',
        'class_id' => 'required|exists:school_classes,id',
        'subject_id' => 'nullable|exists:subjects,id',
        'due_date' => 'nullable|date|after:today',
        'priority' => 'required|in:low,medium,high',
    ];
    
    $validator = app('validator')->make($formData, $rules);
    
    if ($validator->fails()) {
        echo "❌ Validation failed:\n";
        foreach ($validator->errors()->all() as $error) {
            echo "  - {$error}\n";
        }
    } else {
        echo "✅ Validation passed\n";
    }
    
} catch (Exception $e) {
    echo "❌ Validation error: " . $e->getMessage() . "\n";
}

// Test actual creation
echo "\n💾 STEP 2: Testing actual creation\n";
echo "───────────────────────────────────\n";

try {
    $homework = HomeworkNotice::create([
        'title' => $formData['title'],
        'description' => $formData['description'],
        'type' => $formData['type'],
        'class_id' => $formData['class_id'],
        'subject_id' => $formData['subject_id'],
        'assigned_by' => $teacherLogin->id,
        'due_date' => $formData['due_date'],
        'publish_date' => now(),
        'status' => 'active',
        'priority' => $formData['priority'],
    ]);
    
    echo "✅ Homework created successfully!\n";
    echo "📋 ID: {$homework->id}\n";
    echo "📋 Title: {$homework->title}\n";
    echo "📋 Type: {$homework->type}\n";
    echo "📋 Priority: {$homework->priority}\n";
    echo "📋 Assigned by: {$homework->assigned_by}\n";
    
    // Verify in database
    $found = HomeworkNotice::find($homework->id);
    if ($found) {
        echo "✅ Record verified in database\n";
    } else {
        echo "❌ Record not found in database\n";
    }
    
    // Test if it appears in the teacher's homework list
    echo "\n📋 STEP 3: Testing homework retrieval\n";
    echo "─────────────────────────────────────\n";
    
    $teacherHomework = HomeworkNotice::where('assigned_by', $teacherLogin->id)
        ->where('type', 'homework')
        ->get();
    
    echo "✅ Teacher has {$teacherHomework->count()} homework records\n";
    foreach ($teacherHomework as $hw) {
        echo "  - {$hw->title} ({$hw->type})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Creation error: " . $e->getMessage() . "\n";
}

echo "\n🎯 FORM FIX TESTING COMPLETE\n";
