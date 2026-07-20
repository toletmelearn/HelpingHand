<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get the first available SchoolClass ID
$firstClass = \App\Models\SchoolClass::first();

if (!$firstClass) {
    die("No SchoolClasses found in DB!\n");
}

echo "Testing with Source Class ID: " . $firstClass->id . " Name: " . $firstClass->name . "\n";

// Instantiate Controller
$controller = new \App\Http\Controllers\Admin\StudentPromotionController();

// Simulate AJAX call
try {
    $response = $controller->getDestinationClasses($firstClass->id);
    $content = $response->getContent();
    
    // Decode to check count
    $data = json_decode($content, true);
    
    echo "Response Status: " . $response->getStatusCode() . "\n";
    echo "Destinations Found: " . count($data) . "\n";
    if (count($data) > 0) {
        echo "First Destination: ID=" . $data[0]['id'] . " Name=" . $data[0]['name'] . "\n";
    }
    // echo "Full Payload: " . substr($content, 0, 500) . "...\n"; // Preview
    
} catch (\Throwable $e) {
    echo "Controller Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
