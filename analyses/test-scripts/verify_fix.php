<?php
require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Set the Laravel application context
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== VERIFYING PARENT LOGIN FIX ===\n\n";

// Test a few random parents
$testParents = DB::table('parents')
    ->whereNotNull('mobile')
    ->inRandomOrder()
    ->limit(5)
    ->get();

echo "Testing 5 random parents:\n";
foreach ($testParents as $parent) {
    $isValid = password_verify('123456', $parent->password);
    echo "Mobile: {$parent->mobile} | Name: {$parent->name} | Password OK: " . ($isValid ? 'YES' : 'NO') . "\n";
}

// Test the specific working parent
$workingParent = DB::table('parents')->where('mobile', '9842950777')->first();
if ($workingParent) {
    $isValid = password_verify('123456', $workingParent->password);
    echo "\nOriginal working parent (9842950777): " . ($isValid ? 'STILL WORKS' : 'BROKEN') . "\n";
}

echo "\n=== VERIFICATION COMPLETE ===\n";
echo "All parents should now be able to login with password: 123456\n";