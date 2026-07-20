<?php
require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use App\Models\ParentModel;
use Illuminate\Support\Facades\Hash;

// Set the Laravel application context
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== PARENT LOGIN DEBUGGING ===\n\n";

// Step 1: Check parents table
echo "1️⃣ DATABASE CHECK\n";
echo "==================\n";
try {
    $count = DB::table('parents')->count();
    echo "Total parents in database: " . $count . "\n\n";
    
    // Get sample parents to check structure
    $parents = DB::table('parents')->limit(10)->get();
    echo "Sample parents:\n";
    foreach ($parents as $parent) {
        echo "ID: {$parent->id}, Name: {$parent->name}, Mobile: {$parent->mobile}, Admission: {$parent->admission_number}\n";
        echo "Password hash: " . substr($parent->password, 0, 20) . "...\n";
        echo "Password length: " . strlen($parent->password) . " chars\n";
        echo "Is bcrypt hash: " . (strpos($parent->password, '$2y$') === 0 ? 'YES' : 'NO') . "\n\n";
    }
    
    // Check specific problematic parents
    echo "Checking specific parents:\n";
    $workingParent = DB::table('parents')->where('mobile', '9842950777')->first();
    if ($workingParent) {
        echo "Working parent (9842950777):\n";
        echo "Password: {$workingParent->password}\n";
        echo "Hash verification: " . (password_verify('123456', $workingParent->password) ? 'PASS' : 'FAIL') . "\n\n";
    }
    
    // Check a few non-working parents
    $nonWorkingParents = DB::table('parents')
        ->where('mobile', '!=', '9842950777')
        ->whereNotNull('mobile')
        ->limit(3)
        ->get();
    
    echo "Non-working parents sample:\n";
    foreach ($nonWorkingParents as $parent) {
        echo "Mobile: {$parent->mobile}, Name: {$parent->name}\n";
        echo "Password: {$parent->password}\n";
        echo "Hash verification: " . (password_verify('123456', $parent->password) ? 'PASS' : 'FAIL') . "\n\n";
    }
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

// Step 2: Check password hashing consistency
echo "\n2️⃣ PASSWORD HASH ANALYSIS\n";
echo "=========================\n";
$allParents = DB::table('parents')->get();
$hashPatterns = [];
$plainTextCount = 0;
$bcryptCount = 0;

foreach ($allParents as $parent) {
    $password = $parent->password;
    if (strpos($password, '$2y$') === 0) {
        $bcryptCount++;
        // Check if it's a valid bcrypt hash
        if (password_verify('123456', $password)) {
            $hashPatterns['valid_bcrypt_123456'] = ($hashPatterns['valid_bcrypt_123456'] ?? 0) + 1;
        } else {
            $hashPatterns['valid_bcrypt_other'] = ($hashPatterns['valid_bcrypt_other'] ?? 0) + 1;
        }
    } else {
        $plainTextCount++;
        $hashPatterns['plaintext_' . substr($password, 0, 10)] = ($hashPatterns['plaintext_' . substr($password, 0, 10)] ?? 0) + 1;
    }
}

echo "Password analysis:\n";
echo "Bcrypt hashed passwords: {$bcryptCount}\n";
echo "Plain text passwords: {$plainTextCount}\n";
echo "Hash patterns breakdown:\n";
foreach ($hashPatterns as $pattern => $count) {
    echo "  {$pattern}: {$count}\n";
}

// Step 3: Check parent creation code
echo "\n3️⃣ PARENT CREATION CODE CHECK\n";
echo "============================\n";

// Let's look for parent creation in admin controllers
$filesToCheck = [
    'app/Http/Controllers/Admin/AdminStudentController.php',
    'app/Http/Controllers/Admin/StudentController.php',
    'database/seeders/ParentsTableSeeder.php'
];

foreach ($filesToCheck as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'ParentModel') !== false || strpos($content, 'parents') !== false) {
            echo "Found parent-related code in: {$file}\n";
            // Look for password hashing
            if (strpos($content, 'Hash::make') !== false) {
                echo "  ✓ Uses Hash::make for password hashing\n";
            } else {
                echo "  ⚠ May not use proper password hashing\n";
            }
            echo "\n";
        }
    }
}

echo "\n4️⃣ LOGIN CONTROLLER CHECK\n";
echo "========================\n";
$loginController = 'app/Http/Controllers/Parent/ParentAuthController.php';
if (file_exists($loginController)) {
    $content = file_get_contents($loginController);
    echo "ParentAuthController password check method:\n";
    if (strpos($content, 'Hash::check') !== false) {
        echo "  ✓ Uses Hash::check for password verification\n";
    } else {
        echo "  ⚠ Does NOT use Hash::check\n";
    }
    
    if (strpos($content, 'where(\'mobile\'') !== false) {
        echo "  ✓ Searches by mobile column\n";
    } else {
        echo "  ⚠ May not search by mobile column\n";
    }
}

echo "\n=== DEBUG COMPLETE ===\n";