<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Student;
use App\Models\FeeStructure;
use App\Models\FeeType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// 1. Get Clerk & Accountant users
$clerk = User::role('clerk')->first();
$accountant = User::role('accountant')->first();

echo "Clerk: " . ($clerk ? $clerk->email : "None") . "\n";
echo "Accountant: " . ($accountant ? $accountant->email : "None") . "\n";

// 2. Get a Student and Fee Structure
$student = Student::first();
if (!$student) {
    echo "No student found in DB!\n";
    exit;
}
echo "Testing with Student: {$student->name} (Class: {$student->class})\n";

$feeStructure = FeeStructure::where('class_name', $student->class)->first();
if (!$feeStructure) {
    // Let's find any fee structure and assign class to match student
    $feeStructure = FeeStructure::first();
    if ($feeStructure) {
        $student->class = $feeStructure->class_name;
        $student->save();
        echo "Updated Student class to {$student->class} to match Fee Structure: {$feeStructure->name}\n";
    } else {
        echo "No Fee Structure found in DB!\n";
        exit;
    }
} else {
    echo "Found Fee Structure: {$feeStructure->name}\n";
}

$feeTypes = $feeStructure->feeStructureItems;
if ($feeTypes->isEmpty()) {
    echo "No items in Fee Structure!\n";
    exit;
}
$feeTypeId = $feeTypes->first()->fee_type_id;
echo "Testing with Fee Type ID: {$feeTypeId}\n";

// Ensure Yadav has permissions for fees in DB
if ($clerk) {
    echo "Clerk Yadav has 'create-fees' permission? " . ($clerk->hasPermission('create-fees') ? 'Yes' : 'No') . "\n";
}

// 3. Simulate store request for Clerk
Auth::login($clerk);
$controller = app(\App\Http\Controllers\Admin\FeeCollectionController::class);

$requestData = [
    'student_id' => $student->id,
    'fee_structure_id' => $feeStructure->id,
    'fee_type_id' => [$feeTypeId],
    'amount' => [$feeTypeId => 500],
    'payment_mode' => 'cash',
    'remarks' => 'Test collection by Clerk',
    'submission_token' => 'token_' . uniqid()
];

echo "\n--- Simulating Fee Collection (Mode: cash) ---\n";
$request = Request::create('/admin/fees', 'POST', $requestData);
$session = $app->make('session')->driver();
$request->setLaravelSession($session);

try {
    DB::beginTransaction();
    $response = $controller->store($request);
    DB::rollBack();
    echo "Success! Response status: " . $response->getStatusCode() . "\n";
} catch (\Throwable $e) {
    DB::rollBack();
    echo "Failed! Error: " . $e->getMessage() . "\n";
}

// 4. Test with 'card' payment mode
echo "\n--- Simulating Fee Collection (Mode: card) ---\n";
$requestData['payment_mode'] = 'card';
$requestData['submission_token'] = 'token_' . uniqid();
$request = Request::create('/admin/fees', 'POST', $requestData);
$request->setLaravelSession($session);

try {
    DB::beginTransaction();
    $response = $controller->store($request);
    DB::rollBack();
    echo "Success! Response status: " . $response->getStatusCode() . "\n";
} catch (\Throwable $e) {
    DB::rollBack();
    echo "Failed! Error: " . $e->getMessage() . "\n";
}
