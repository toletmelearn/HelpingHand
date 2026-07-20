<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Admin\StudentPromotionController;
use Illuminate\Http\Request;

$controller = new StudentPromotionController();

echo "=== TESTING getDestinationClasses(1) ===\n";
$response = $controller->getDestinationClasses(1);
echo "Status code: " . $response->getStatusCode() . "\n";
echo "Content: " . $response->getContent() . "\n\n";

echo "=== TESTING getStudentsByClass(1) ===\n";
$response2 = $controller->getStudentsByClass(1);
echo "Status code: " . $response2->getStatusCode() . "\n";
echo "Content: " . $response2->getContent() . "\n\n";
