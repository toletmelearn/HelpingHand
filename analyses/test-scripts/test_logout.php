<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Bootstrap the application
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Login a student first
$student = App\Models\Student::where('mobile', '9876543211')->first();
\Illuminate\Support\Facades\Auth::guard('parent')->login($student);

echo "Before logout - Auth check: " . (\Illuminate\Support\Facades\Auth::guard('parent')->check() ? 'true' : 'false') . "\n";

// Create a request
$request = Illuminate\Http\Request::create('/parent/logout', 'POST');

// Set the request as the current request
$app->instance('request', $request);

// Get the controller
$controller = $app->make('App\Http\Controllers\Parent\ParentAuthController');

// Call the logout method
$response = $controller->logout($request);

echo "After logout - Auth check: " . (\Illuminate\Support\Facades\Auth::guard('parent')->check() ? 'true' : 'false') . "\n";
echo "Response type: " . get_class($response) . "\n";
if ($response instanceof Illuminate\Http\RedirectResponse) {
    echo "Redirect URL: " . $response->getTargetUrl() . "\n";
}