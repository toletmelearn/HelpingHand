<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Student;
use App\Models\FeeStructure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$clerk = User::role('clerk')->first();
$student = Student::first();
$feeStructure = FeeStructure::first();

if (!$student || !$feeStructure) {
    echo "Required data missing!\n";
    exit;
}

$feeTypeId = $feeStructure->feeStructureItems->first()->fee_type_id;

Auth::login($clerk);
$controller = app(\App\Http\Controllers\Admin\FeeCollectionController::class);

$paymentModes = ['cash', 'upi', 'bank', 'card', 'online'];

foreach ($paymentModes as $mode) {
    echo "\nTesting payment mode: {$mode}\n";
    $requestData = [
        'student_id' => $student->id,
        'fee_structure_id' => $feeStructure->id,
        'fee_type_id' => [$feeTypeId],
        'amount' => [$feeTypeId => 500],
        'payment_mode' => $mode,
        'remarks' => 'Test collection',
        'submission_token' => 'token_' . uniqid()
    ];
    
    $request = Request::create('/admin/fees', 'POST', $requestData);
    $session = $app->make('session')->driver();
    $request->setLaravelSession($session);
    
    try {
        DB::beginTransaction();
        $response = $controller->store($request);
        DB::rollBack();
        
        echo "Response Status: " . $response->getStatusCode() . "\n";
        echo "Target URL: " . $response->headers->get('Location') . "\n";
        
        $error = $session->get('error');
        $success = $session->get('success');
        if ($error) {
            echo "Session ERROR: " . $error . "\n";
        }
        if ($success) {
            echo "Session SUCCESS: " . $success . "\n";
        }
    } catch (\Throwable $e) {
        DB::rollBack();
        echo "Threw Exception: " . $e->getMessage() . "\n";
    }
}
