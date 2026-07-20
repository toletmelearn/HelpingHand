<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Create a request with login data
$request = Illuminate\Http\Request::create('/parent/login', 'POST', [
    '_token' => csrf_token(),
    'mobile' => '9876543211',
    'password' => '123456'
]);

// Set the request as the current request
$app->instance('request', $request);

// Handle the request
$response = $app->handle($request);

echo "Status Code: " . $response->getStatusCode() . "\n";
echo "Location: " . $response->headers->get('Location') . "\n";
echo "Content: " . substr($response->getContent(), 0, 200) . "...\n";