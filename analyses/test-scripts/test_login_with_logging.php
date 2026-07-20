<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Bootstrap the application
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test the login with logging
$request = Illuminate\Http\Request::create('/parent/login', 'POST', [
    'mobile' => '9876543211',
    'password' => 'STU001'
]);

$app->instance('request', $request);
$controller = $app->make('App\Http\Controllers\Parent\ParentAuthController');

echo "Testing login with logging...\n";
$response = $controller->login($request);

echo "Response type: " . get_class($response) . "\n";
if ($response instanceof Illuminate\Http\RedirectResponse) {
    echo "Redirect URL: " . $response->getTargetUrl() . "\n";
    echo "Status: " . $response->status() . "\n";
}

echo "Auth check: " . (auth()->guard('parent')->check() ? 'YES' : 'NO') . "\n";