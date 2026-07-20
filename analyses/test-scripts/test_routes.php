<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Route;

echo "=== ROUTE TESTING ===\n\n";

// Test if the route exists
$routeExists = Route::has('admin.results.generate-form');
echo "Route 'admin.results.generate-form' exists: " . ($routeExists ? 'Yes' : 'No') . "\n";

if ($routeExists) {
    try {
        $url = route('admin.results.generate-form');
        echo "Route URL: " . $url . "\n";
    } catch (Exception $e) {
        echo "Error generating route URL: " . $e->getMessage() . "\n";
    }
} else {
    echo "Route not found in route definitions\n";
}

echo "\n";

// Check what admin result routes exist
echo "Checking admin result routes:\n";
$routes = Route::getRoutes();
$adminResultRoutes = [];

foreach ($routes as $route) {
    $name = $route->getName();
    if ($name && strpos($name, 'admin.results') !== false) {
        $adminResultRoutes[] = $name;
    }
}

if (count($adminResultRoutes) > 0) {
    foreach ($adminResultRoutes as $routeName) {
        echo "- " . $routeName . "\n";
    }
} else {
    echo "No admin.results routes found\n";
}