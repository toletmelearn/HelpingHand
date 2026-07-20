<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Bootstrap the application
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test the login route directly
echo "Testing route generation:\n";
echo "parent.login: " . route('parent.login') . "\n";
echo "parent.login.post: " . route('parent.login.post') . "\n";

// Test controller method
$request = Illuminate\Http\Request::create('/parent/login', 'POST', [
    'mobile' => '9876543211',
    'password' => 'STU001'
]);

$app->instance('request', $request);
$controller = $app->make('App\Http\Controllers\Parent\ParentAuthController');
$response = $controller->login($request);

echo "\nController response: " . get_class($response) . "\n";
if ($response instanceof Illuminate\Http\RedirectResponse) {
    echo "Redirect URL: " . $response->getTargetUrl() . "\n";
}