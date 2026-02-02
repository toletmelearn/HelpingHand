<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;

$app = require_once 'bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

try {
    $tables = DB::select('SHOW TABLES');
    echo "Tables in database:\n";
    foreach ($tables as $table) {
        $tableName = array_values((array) $table)[0];
        echo "- $tableName\n";
    }
    
    // Check if lesson_plans table exists
    $lessonPlansTable = DB::select("SHOW TABLES LIKE 'lesson_plans'");
    if (empty($lessonPlansTable)) {
        echo "\nlesson_plans table does not exist. Running migration...\n";
        \Artisan::call('migrate', ['--force' => true]);
        echo "Migration output: " . \Artisan::output();
    } else {
        echo "\nlesson_plans table already exists.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}