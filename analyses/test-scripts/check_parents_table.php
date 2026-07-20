<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CHECKING PARENTS TABLE ===\n\n";

// Check if parents table exists and get column listing
try {
    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('parents');
    echo "Parents table columns:\n";
    foreach ($columns as $column) {
        echo "- " . $column . "\n";
    }
    
    echo "\nChecking if student_id column exists: ";
    if (in_array('student_id', $columns)) {
        echo "YES\n";
    } else {
        echo "NO\n";
    }
    
    // Check sample data
    echo "\nSample parent records:\n";
    $parents = \Illuminate\Support\Facades\DB::table('parents')->limit(5)->get();
    
    if ($parents->count() > 0) {
        foreach ($parents as $parent) {
            echo "Parent ID: " . $parent->id . "\n";
            echo "Name: " . ($parent->name ?? 'N/A') . "\n";
            echo "Student ID: " . ($parent->student_id ?? 'NULL') . "\n";
            echo "---\n";
        }
    } else {
        echo "No parent records found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== CHECK COMPLETE ===\n";