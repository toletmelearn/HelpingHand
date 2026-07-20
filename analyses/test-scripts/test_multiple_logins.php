<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Bootstrap the application
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test login with a newly updated student
$request = Illuminate\Http\Request::create('/parent/login', 'POST', [
    'mobile' => '9876543214',
    'password' => 'STU005'
]);

// Set the request as the current request
$app->instance('request', $request);

// Get the controller
$controller = $app->make('App\Http\Controllers\Parent\ParentAuthController');

// Call the login method directly
$response = $controller->login($request);

echo "Response type: " . get_class($response) . "\n";
if ($response instanceof Illuminate\Http\RedirectResponse) {
    echo "Redirect URL: " . $response->getTargetUrl() . "\n";
} else {
    echo "Response content: " . $response->getContent() . "\n";
}

// Also test with the original student
echo "\n--- Testing original student ---\n";
$request2 = Illuminate\Http\Request::create('/parent/login', 'POST', [
    'mobile' => '9876543211',
    'password' => 'STU001'
]);

$app->instance('request', $request2);
$response2 = $controller->login($request2);

echo "Response type: " . get_class($response2) . "\n";
if ($response2 instanceof Illuminate\Http\RedirectResponse) {
    echo "Redirect URL: " . $response2->getTargetUrl() . "\n";
}