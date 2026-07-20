<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Create a request
$request = Illuminate\Http\Request::create('/parent/login', 'GET');

// Get the router
$router = $app->make('router');

// Try to match the route
try {
    $route = $router->getRoutes()->match($request);
    echo "Route found!\n";
    echo "URI: " . $route->uri() . "\n";
    echo "Action: " . json_encode($route->getAction()) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}