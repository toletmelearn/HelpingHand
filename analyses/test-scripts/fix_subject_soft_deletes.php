<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    // Check if deleted_at column exists
    $columns = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM subjects LIKE 'deleted_at'");
    
    if (empty($columns)) {
        // Add the deleted_at column
        \Illuminate\Support\Facades\Schema::table('subjects', function ($table) {
            $table->softDeletes();
        });
        echo "Added deleted_at column to subjects table\n";
    } else {
        echo "deleted_at column already exists\n";
    }
    
    // Test the withTrashed method
    $subjects = \App\Models\Subject::withTrashed()->count();
    echo "Successfully retrieved subjects with withTrashed(). Count: " . $subjects . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}