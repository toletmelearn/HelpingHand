<?php
require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check if the library settings table exists
try {
    $exists = Schema::hasTable('library_settings');
    if ($exists) {
        echo "Library settings table exists.\n";
        $settings = App\Models\LibrarySetting::getSetting();
        echo "Default settings loaded: ";
        print_r($settings->toArray());
    } else {
        echo "Library settings table does not exist. Running migration...\n";
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_01_28_074138_create_library_settings_table.php'
        ]);
        echo "Migration output: " . Artisan::output();
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}