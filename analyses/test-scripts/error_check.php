<?php
// Simple error checking script

echo "=== Comprehensive Error Check ===\n\n";

// Check 1: Basic PHP functionality
echo "1. PHP Version Check:\n";
echo "   PHP Version: " . PHP_VERSION . "\n";
echo "   ✅ PHP is working\n\n";

// Check 2: File existence
echo "2. Required Files Check:\n";
$files = [
    'app/Models/Attendance.php',
    'app/Http/Controllers/AttendanceController.php',
    'routes/web.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file exists\n";
    } else {
        echo "   ❌ $file missing\n";
    }
}
echo "\n";

// Check 3: Syntax check
echo "3. PHP Syntax Check:\n";
$syntaxFiles = [
    'app/Models/Attendance.php',
    'app/Http/Controllers/AttendanceController.php'
];

foreach ($syntaxFiles as $file) {
    $result = exec("php -l $file 2>&1", $output, $returnCode);
    if ($returnCode === 0) {
        echo "   ✅ $file syntax OK\n";
    } else {
        echo "   ❌ $file syntax ERROR\n";
        echo "      " . implode("\n      ", $output) . "\n";
    }
}
echo "\n";

// Check 4: Class loading test
echo "4. Class Loading Test:\n";
try {
    require_once 'vendor/autoload.php';
    
    // Test if we can load the Attendance model
    if (class_exists('App\\Models\\Attendance')) {
        echo "   ✅ Attendance model loads correctly\n";
    } else {
        echo "   ❌ Attendance model failed to load\n";
    }
    
    // Test if we can load the AttendanceController
    if (class_exists('App\\Http\\Controllers\\AttendanceController')) {
        echo "   ✅ AttendanceController loads correctly\n";
    } else {
        echo "   ❌ AttendanceController failed to load\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Class loading error: " . $e->getMessage() . "\n";
}
echo "\n";

// Check 5: Route file syntax
echo "5. Routes File Check:\n";
$routeResult = exec("php -l routes/web.php 2>&1", $routeOutput, $routeReturnCode);
if ($routeReturnCode === 0) {
    echo "   ✅ routes/web.php syntax OK\n";
} else {
    echo "   ❌ routes/web.php syntax ERROR\n";
    echo "      " . implode("\n      ", $routeOutput) . "\n";
}
echo "\n";

echo "=== CHECK COMPLETE ===\n";
echo "If all checks show ✅, the system should be ready to run.\n";
echo "Try running: php artisan serve\n";
?>