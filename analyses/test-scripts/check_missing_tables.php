<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Get all migrations that have been run
$ranMigrations = DB::table('migrations')->pluck('migration')->toArray();

// List of expected tables based on migrations
$expectedTables = [
    'bell_schedules',
    'special_day_overrides',
    'teacher_substitutions',
    'biometric_devices',
    'biometric_sync_logs',
    'notification_templates',
    'performance_scores',
    'self_service_tokens',
    'teacher_biometric_records',
    'biometric_settings',
    'working_hours_configurations',
];

echo "Checking for missing tables...\n\n";

$missingTables = [];
foreach ($expectedTables as $table) {
    if (!Schema::hasTable($table)) {
        $missingTables[] = $table;
        echo "❌ MISSING: $table\n";
    } else {
        echo "✅ EXISTS: $table\n";
    }
}

echo "\n";
if (empty($missingTables)) {
    echo "All checked tables exist!\n";
} else {
    echo "Missing tables: " . implode(', ', $missingTables) . "\n";
}
