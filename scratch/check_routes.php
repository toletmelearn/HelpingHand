<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$routes = Route::getRoutes();
foreach ($routes as $route) {
    if (strpos($route->uri(), 'exam-paper') !== false || strpos($route->getName(), 'exam-paper') !== false) {
        echo "URI: " . $route->uri() . "\n";
        echo "Name: " . $route->getName() . "\n";
        echo "Action: " . $route->getActionName() . "\n\n";
    }
}
