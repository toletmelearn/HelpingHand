<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Bootstrap the application
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test login with INVALID credentials
$request = Illuminate\Http\Request::create('/parent/login', 'POST', [
    'mobile' => '9876543211',
    'password' => 'WRONGPASSWORD'
]);

$app->instance('request', $request);
$controller = $app->make('App\Http\Controllers\Parent\ParentAuthController');

echo "Testing login with INVALID credentials...\n";
$response = $controller->login($request);

echo "Response type: " . get_class($response) . "\n";
if ($response instanceof Illuminate\Http\RedirectResponse) {
    echo "Redirect URL: " . $response->getTargetUrl() . "\n";
    echo "Status code: " . $response->status() . "\n";
}

echo "\nAuth check: " . (auth()->guard('parent')->check() ? 'YES' : 'NO') . "\n";
echo "Session error: " . (session('error') ? session('error') : 'No error') . "\n";