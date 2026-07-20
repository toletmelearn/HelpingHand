<?php
require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use App\Models\ParentModel;

// Set the Laravel application context
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    // Check if parents table exists and has records
    $count = DB::table('parents')->count();
    echo "Total parent records: " . $count . "\n";
    
    // Fetch a few parent records to inspect
    $parents = DB::table('parents')->limit(5)->get();
    foreach ($parents as $parent) {
        echo "ID: {$parent->id}, Name: {$parent->name}, Mobile: {$parent->mobile}, Admission: {$parent->admission_number}\n";
        echo "Password (first 20 chars): " . substr($parent->password, 0, 20) . "...\n";
        echo "Full password hash: {$parent->password}\n\n";
    }
    
    // Check if a specific parent exists (the one mentioned in the query)
    $specificParent = DB::table('parents')->where('mobile', '9842950777')->first();
    if ($specificParent) {
        echo "Found specific parent with mobile 9842950777:\n";
        echo "ID: {$specificParent->id}, Name: {$specificParent->name}\n";
        echo "Password hash: {$specificParent->password}\n";
        
        // Test password verification
        $isValid = password_verify('123456', $specificParent->password);
        echo "Password '123456' verification: " . ($isValid ? 'TRUE' : 'FALSE') . "\n";
    } else {
        echo "No parent found with mobile 9842950777\n";
    }
    
    // Also check by admission number
    $admissionParent = DB::table('parents')->where('admission_number', '9842950777')->first();
    if ($admissionParent) {
        echo "Found parent by admission number 9842950777:\n";
        echo "ID: {$admissionParent->id}, Name: {$admissionParent->name}\n";
        echo "Password hash: {$admissionParent->password}\n";
        
        // Test password verification
        $isValid = password_verify('123456', $admissionParent->password);
        echo "Password '123456' verification: " . ($isValid ? 'TRUE' : 'FALSE') . "\n";
    } else {
        echo "No parent found with admission number 9842950777\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}