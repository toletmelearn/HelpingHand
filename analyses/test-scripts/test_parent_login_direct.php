<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Create a request with login data
$request = Illuminate\Http\Request::create('/parent/login', 'POST', [
    'mobile' => '9876543211',
    'password' => '123456'
]);

// Set the request as the current request
$app->instance('request', $request);

// Get the controller
$controller = $app->make('App\Http\Controllers\Parent\ParentAuthController');

// Call the login method directly
$response = $controller->login($request);

echo "Response: " . get_class($response) . "\n";
echo "Redirect URL: " . $response->getTargetUrl() . "\n";