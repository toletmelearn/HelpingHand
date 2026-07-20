<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;
use App\Models\Role;
use App\Models\Permission;
use App\Models\SchoolClass;
use App\Models\User;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== VERIFYING PROPER SEEDING ===\n\n";

// Check roles
$adminRole = Role::where('name', 'admin')->first();
$teacherRole = Role::where('name', 'teacher')->first();

echo "Roles:\n";
echo "  Admin role: " . ($adminRole ? 'Found' : 'Not found') . "\n";
echo "  Teacher role: " . ($teacherRole ? 'Found' : 'Not found') . "\n";

if ($adminRole) {
    echo "  Admin permissions: " . $adminRole->permissions->count() . "\n";
}

// Check school classes
echo "\nSchool Classes:\n";
echo "  Total classes: " . SchoolClass::count() . "\n";
echo "  Active classes: " . SchoolClass::where('is_active', 1)->count() . "\n";

// Check admin user
$adminUser = User::where('email', 'admin@school.com')->first();
echo "\nAdmin User:\n";
echo "  Admin user: " . ($adminUser ? 'Found' : 'Not found') . "\n";
if ($adminUser) {
    echo "  Has admin role: " . ($adminUser->hasRole('admin') ? 'Yes' : 'No') . "\n";
}

echo "\n=== SEEDING VERIFICATION COMPLETE ===\n";