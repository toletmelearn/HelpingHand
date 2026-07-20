<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Bootstrap the application
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Create a request with login data using mobile number
$request = Illuminate\Http\Request::create('/parent/login', 'POST', [
    'identifier' => '9876543211',
    'password' => '123456'
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