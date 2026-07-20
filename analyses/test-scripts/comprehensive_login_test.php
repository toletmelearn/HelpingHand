<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Bootstrap the application
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== ROUTE TESTING ===\n";
echo "GET route: " . route('parent.login') . "\n";
echo "POST route: " . route('parent.login.post') . "\n";
echo "Dashboard route: " . route('parent.dashboard') . "\n";

echo "\n=== CONTROLLER TESTING ===\n";
// Test with valid credentials
$request = Illuminate\Http\Request::create('/parent/login', 'POST', [
    'mobile' => '9876543211',
    'password' => 'STU001'
]);

$app->instance('request', $request);
$controller = $app->make('App\Http\Controllers\Parent\ParentAuthController');

try {
    $response = $controller->login($request);
    echo "Login response type: " . get_class($response) . "\n";
    if ($response instanceof Illuminate\Http\RedirectResponse) {
        echo "Redirect URL: " . $response->getTargetUrl() . "\n";
        echo "Status code: " . $response->status() . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== AUTH STATE CHECK ===\n";
echo "Is parent authenticated: " . (auth()->guard('parent')->check() ? 'YES' : 'NO') . "\n";

// Test with invalid credentials
echo "\n=== INVALID CREDENTIALS TEST ===\n";
$request2 = Illuminate\Http\Request::create('/parent/login', 'POST', [
    'mobile' => '9876543211',
    'password' => 'WRONG'
]);

$app->instance('request', $request2);
try {
    $response2 = $controller->login($request2);
    echo "Invalid login response type: " . get_class($response2) . "\n";
    if ($response2 instanceof Illuminate\Http\RedirectResponse) {
        echo "Redirect URL: " . $response2->getTargetUrl() . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}