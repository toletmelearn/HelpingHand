<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Hash;
use App\Models\User;

$user = User::where('email', 'admin@school.com')->first();
if ($user) {
    $matches = Hash::check('password', $user->password);
    echo $matches ? 'Password matches' : 'Password does not match';
    echo "\nPassword hash: " . $user->password;
    echo "\nExpected hash: " . Hash::make('password');
} else {
    echo 'User not found';
}
?>