<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Try to register a test route
$router = app('router');
$router->get('test-route', function() { return 'test'; })->name('test.route');

echo "Test route registered: " . (Illuminate\Support\Facades\Route::has('test.route') ? 'YES' : 'NO') . "\n";

// Check if the performance analytics route exists
echo "Performance analytics route exists: " . (Illuminate\Support\Facades\Route::has('admin.admin.performance-analytics') ? 'YES' : 'NO') . "\n";

// List all routes that contain 'performance-analytics'
$routes = collect(Illuminate\Support\Facades\Route::getRoutes())
    ->filter(function ($route) {
        return strpos($route->getName(), 'performance-analytics') !== false;
    })
    ->pluck('name');

echo "Performance analytics routes:\n";
foreach ($routes as $route) {
    echo "- " . $route . "\n";
}