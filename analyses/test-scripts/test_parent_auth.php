<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Bootstrap the application
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test login with valid credentials
$request = Illuminate\Http\Request::create('/parent/login', 'POST', [
    'mobile' => '9876543211',
    'password' => 'STU001'
]);

$app->instance('request', $request);
$controller = $app->make('App\Http\Controllers\Parent\ParentAuthController');

echo "Testing login with valid credentials...\n";
$response = $controller->login($request);

echo "Response type: " . get_class($response) . "\n";
if ($response instanceof Illuminate\Http\RedirectResponse) {
    echo "Redirect URL: " . $response->getTargetUrl() . "\n";
    echo "Status code: " . $response->status() . "\n";
}

echo "\nAuth check: " . (auth()->guard('parent')->check() ? 'YES' : 'NO') . "\n";

// Test dashboard access
if (auth()->guard('parent')->check()) {
    echo "\nTesting dashboard access...\n";
    $dashboardResponse = $controller->dashboard();
    echo "Dashboard response type: " . get_class($dashboardResponse) . "\n";
}