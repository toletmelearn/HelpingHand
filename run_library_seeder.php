<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Run the seeder
try {
    Artisan::call('db:seed', [
        '--class' => 'LibrarySettingSeeder'
    ]);
    echo "Seeder output: " . Artisan::output();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}