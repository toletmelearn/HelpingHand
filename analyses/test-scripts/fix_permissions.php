<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;
use App\Models\Role;
use App\Models\User;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== FIXING ADMIN PERMISSIONS ===\n\n";

// Create Admin role
$adminRole = Role::firstOrCreate([
    'name' => 'Admin',
    'display_name' => 'Administrator',
    'description' => 'Full system administrator'
]);

echo "Admin role created/verified: {$adminRole->name}\n";

// Find admin user
$adminUser = User::where('email', 'admin@school.com')->first();

if ($adminUser) {
    echo "Admin user found: {$adminUser->name} ({$adminUser->email})\n";
    
    // Assign role to user
    if (!$adminUser->hasRole('Admin')) {
        $adminUser->assignRole('Admin');
        echo "✅ Admin role assigned to user\n";
    } else {
        echo "✅ User already has Admin role\n";
    }
} else {
    echo "❌ Admin user not found\n";
}

echo "\n=== PERMISSIONS FIXED ===\n";