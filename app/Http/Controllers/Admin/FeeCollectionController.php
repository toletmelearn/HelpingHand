<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FeeCollection;
use App\Models\FeeCollectionItem;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\StudentFeeAssignment;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facades\PDF;
use App\Services\FeePaymentLockService;

class FeeCollectionController extends Controller
{
    protected $lockService;

    public function __construct(FeePaymentLockService $lockService)
    {
        $this->middleware('auth');
        $this->lockService = $lockService;
    }

    public function index()
    {
        try {
            $todayCollection = FeeCollection::today()->sum('final_amount');
        } catch (\Exception $e) {
            $todayCollection = 0;
        }
        
        try {
            $monthlyCollection = FeeCollection::currentMonth()->sum('final_amount');
        } catch (\Exception $e) {
            $monthlyCollection = 0;
        }
        
        try {
            $pendingFees = $this->getPendingFeesCount();
        } catch (\Exception $e) {
            $pendingFees = 0;
        }
        
        try {
            $totalStudents = Student::count();
        } catch (\Exception $e) {
            $totalStudents = 0;
        }

        try {
            $classes = SchoolClass::active()->orderByOrder()->get();
        } catch (\Exception $e) {
            $classes = collect();
        }

        return view('admin.fees.index', compact('todayCollection', 'monthlyCollection', 'pendingFees', 'totalStudents', 'classes'));
    }

    public function searchStudents(Request $request)
    {
        try {
            $query = Student::withTrashed()->with('schoolClass');

            if ($request->filled('name')) {
                $query->where('name', 'LIKE', '%' . $request->name . '%');
            }

            if ($request->filled('admission_no')) {
                $query->where('admission_no', $request->admission_no);
            }

            if ($request->filled('mobile')) {
                $query->where('mobile', 'LIKE', '%' . $request->mobile . '%');
            }

            if ($request->filled('class_id')) {
                $query->where(function($q) use ($request) {
                    $q->where('class_id', $request->class_id)
                      ->orWhere('school_class_id', $request->class_id);
                });
            }

            $students = $query->limit(100)->get();

            if ($students->count() == 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'No student found'
                ]);
            }

            return response()->json([
                'status' => true,
                'students' => $students
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Search failed',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getStudentFeeDashboard($studentId)
    {
        $student = Student::withTrashed()->with([
            'feeAssignments.feeStructure.feeStructureItems.feeType',
            'feeCollections.feeCollectionItems.feeType',
            'schoolClass'
        ])->findOrFail($studentId);

        $feeAssignments = $student->feeAssignments;
        $frequency = 'monthly';
        
        if ($feeAssignments->first() && $feeAssignments->first()->feeStructure) {
            $frequency = $feeAssignments->first()->feeStructure->frequency;
        }
        
        $feeData = $this->getFeeDataForStudent($student, $frequency);

        $studentFeeAssignment = $student->feeAssignments()->latest()->first();
        $feeStructure = $studentFeeAssignment ? $studentFeeAssignment->feeStructure : null;

        if (!$feeStructure) {
            $schoolClass = $student->resolveCanonicalSchoolClass();
            $className = $schoolClass ? $schoolClass->name : (is_string($student->class) ? $student->class : '');
            $feeStructure = FeeStructure::where('class_name', $className)
                ->active()
                ->first();
        }

        $feeCollections = FeeCollection::where('student_id', $studentId)
            ->latest()
            ->get();

        return view('admin.fees.student-dashboard', [
            'student' => $student,
            'feeData' => $feeData ?? [],
            'frequency' => $frequency ?? 'monthly',
            'feeStructure' => $feeStructure ?? null,
            'feeCollections' => $feeCollections ?? []
        ]);
    }

    private function getPaymentStatus($student, $month, $feeTypeId)
    {
        try {
            $entries = DB::table('student_fee_ledgers')
                ->where('student_id', $student->id)
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $debits = [];
            $credits = [];
            foreach ($entries as $entry) {
                if ($entry->debit > 0) {
                    $debits[] = [
                        'id' => $entry->id,
                        'debit' => (float)$entry->debit,
                        'remaining' => (float)$entry->debit,
                        'reference_type' => $entry->reference_type,
                        'reference_id' => $entry->reference_id,
                        'description' => $entry->description,
                    ];
                }
                if ($entry->credit > 0) {
                    $credits[] = (float)$entry->credit;
                }
            }

            // Match credits against debits in FIFO order
            $creditIndex = 0;
            $creditCount = count($credits);
            $currentCredit = $creditCount > 0 ? $credits[0] : 0;

            foreach ($debits as &$debit) {
                while ($debit['remaining'] > 0 && $creditIndex < $creditCount) {
                    if ($currentCredit >= $debit['remaining']) {
                        $currentCredit -= $debit['remaining'];
                        $debit['remaining'] = 0;
                    } else {
                        $debit['remaining'] -= $currentCredit;
                        $currentCredit = 0;
                    }

                    if ($currentCredit == 0) {
                        $creditIndex++;
                        if ($creditIndex < $creditCount) {
                            $currentCredit = $credits[$creditIndex];
                        }
                    }
                }
            }
            unset($debit);

            // Now, find the specific debit for this month and fee type
            $foundDebit = null;
            foreach ($debits as $debit) {
                $isMatch = false;

                if ($debit['reference_type'] === 'fee_structure_item') {
                    $item = DB::table('fee_structure_items')->where('id', $debit['reference_id'])->first();
                    if ($item && $item->fee_type_id == $feeTypeId) {
                        if (str_contains($debit['description'], $month)) {
                            $isMatch = true;
                        }
                    }
                } elseif ($debit['reference_type'] === 'student_transport_due') {
                    $transportFeeType = DB::table('fee_types')->where('name', 'Transport Fee')->first();
                    if ($transportFeeType && $transportFeeType->id == $feeTypeId) {
                        $due = DB::table('student_transport_dues')->where('id', $debit['reference_id'])->first();
                        if ($due && $due->month == $month) {
                            $isMatch = true;
                        }
                    }
                }

                if ($isMatch) {
                    $foundDebit = $debit;
                    break;
                }
            }

            if ($foundDebit) {
                return $foundDebit['remaining'] <= 0 ? 'Paid' : 'Unpaid';
            }

            // Fallback: If no debit charge exists for this month, check if a collection exists (legacy behavior)
            $collection = $student->feeCollections()
                ->whereHas('feeCollectionItems', function($query) use ($feeTypeId) {
                    $query->where('fee_collection_items.fee_type_id', $feeTypeId);
                })
                ->first();

            return $collection ? 'Paid' : 'Unpaid';
        } catch (\Exception $e) {
            return 'Unpaid';
        }
    }
    
    private function getCollectionId($student, $feeTypeId)
    {
        $collection = $student->feeCollections()
            ->whereHas('feeCollectionItems', function($query) use ($feeTypeId) {
                $query->where('fee_collection_items.fee_type_id', $feeTypeId);
            })
            ->latest()
            ->first();

        return $collection ? $collection->id : null;
    }
    
    private function getFeeDataForStudent($student, $frequency)
    {
        $feeAssignments = $student->feeAssignments;
        $feeData = [];
        
        foreach ($feeAssignments as $assignment) {
            $structure = $assignment->feeStructure;
            $months = $this->generateMonths($frequency);
            
            foreach ($months as $month) {
                foreach ($structure->feeStructureItems as $item) {
                    $status = $this->getPaymentStatus($student, $month, $item->feeType->id);
                    $collectionId = $this->getCollectionId($student, $item->feeType->id);
                    
                    $feeData[] = [
                        'month' => $month,
                        'fee_type' => $item->feeType->name,
                        'amount' => $item->amount,
                        'due_day' => $item->due_day,
                        'status' => $status,
                        'collection_id' => $collectionId,
                        'fee_structure_item_id' => $item->id
                    ];
                }
            }
        }
        
        return $feeData;
    }

    private function generateMonths($frequency)
    {
        switch ($frequency) {
            case 'monthly':
                return ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];
            case 'quarterly':
                return ['Q1', 'Q2', 'Q3', 'Q4'];
            case 'yearly':
                return ['Annual'];
            default:
                return ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];
        }
    }

    public function collectFee(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'month' => 'nullable|string',
            'fee_type_id' => 'required|exists:fee_types,id',
            'amount' => 'required|numeric|min:0',
            'late_fine' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'payment_mode' => 'required|in:' . implode(',', FeeCollection::PAYMENT_MODES),
            'transaction_id' => [
                \Illuminate\Validation\Rule::requiredIf(function () use ($request) {
                    return in_array($request->payment_mode, ['bank', 'online']);
                }),
                'nullable',
                'string',
                'max:100'
            ],
            'payment_date' => 'nullable|date',
            'submission_token' => 'nullable|string'
        ]);

        if (empty($validated['payment_date'])) {
            $validated['payment_date'] = now()->format('Y-m-d');
        }
        if (empty($validated['month'])) {
            $validated['month'] = \Carbon\Carbon::parse($validated['payment_date'])->format('F');
        }

        $collectorId = auth('web')->id();

        if (!$collectorId) {
            abort(403, 'Authenticated admin user required for fee collection.');
        }

        // Generate fingerprint
        $fingerprint = $this->lockService->generateFingerprint($validated, $collectorId);
        
        $hasToken = !empty($validated['submission_token']);
        $token = $validated['submission_token'] ?? null;

        if ($hasToken && !$this->lockService->acquireTokenLock($token)) {
            return response()->json([
                'status' => false,
                'message' => 'This payment is already being processed.'
            ], 409);
        }

        if (!$this->lockService->acquireFingerprintLock($fingerprint)) {
            if ($hasToken) {
                $this->lockService->releaseTokenLock($token);
            }
            return response()->json([
                'status' => false,
                'message' => 'This payment is already being processed.'
            ], 409);
        }

        // Sequential Fee Collection Enforcement (Strict Chronology)
        $student = Student::withTrashed()->findOrFail($validated['student_id']);
        $feeStructureId = $this->getFeeStructureId($validated['student_id']);
        $feeStructure = \App\Models\FeeStructure::find($feeStructureId);
        $academicYear = $feeStructure ? $feeStructure->academic_year : '2026-27';

        if ($this->hasPriorMonthOutstanding($validated['student_id'], $validated['month'], $academicYear)) {
            if ($hasToken) {
                $this->lockService->releaseTokenLock($token);
            }
            $this->lockService->releaseFingerprintLock($fingerprint);
            return response()->json([
                'status' => false,
                'message' => 'Cannot process payment. Prior month balances must be cleared first.'
            ], 422);
        }

        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                DB::beginTransaction();

                $totalAmount = $validated['amount'];
                $lateFine = $validated['late_fine'] ?? 0;
                $discount = $validated['discount'] ?? 0;
                $finalAmount = $totalAmount + $lateFine - $discount;

                $feeCollection = FeeCollection::create([
                    'receipt_no' => FeeCollection::generateReceiptNumber(),
                    'student_id' => $validated['student_id'],
                    'fee_structure_id' => $feeStructureId,
                    'total_amount' => $totalAmount,
                    'discount' => $discount,
                    'late_fine' => $lateFine,
                    'final_amount' => $finalAmount,
                    'payment_date' => $validated['payment_date'],
                    'payment_mode' => $validated['payment_mode'],
                    'transaction_id' => $validated['transaction_id'] ?? null,
                    'remarks' => 'Fee collected for ' . $validated['month'],
                    'collected_by' => $collectorId,
                    'fee_month' => $validated['month']
                ]);

                FeeCollectionItem::create([
                    'fee_collection_id' => $feeCollection->id,
                    'fee_type_id' => $validated['fee_type_id'],
                    'amount' => $totalAmount
                ]);

                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'Fee collected successfully',
                    'receipt_no' => $feeCollection->receipt_no,
                    'collection_id' => $feeCollection->id
                ]);

            } catch (\Illuminate\Database\QueryException $e) {
                DB::rollback();
                $isUniqueViolation = false;
                if ($e->getCode() == 23000 || strpos($e->getMessage(), 'unique constraint') !== false || strpos($e->getMessage(), 'UNIQUE') !== false) {
                    $isUniqueViolation = true;
                }
                
                if ($isUniqueViolation && $attempt < $maxAttempts) {
                    continue;
                }

                if ($hasToken) {
                    $this->lockService->releaseTokenLock($token);
                }
                $this->lockService->releaseFingerprintLock($fingerprint);

                return response()->json([
                    'status' => false,
                    'message' => 'Database collision detected. Please try again.',
                    'error' => $e->getMessage()
                ], 409);
            } catch (\Exception $e) {
                DB::rollback();

                if ($hasToken) {
                    $this->lockService->releaseTokenLock($token);
                }
                $this->lockService->releaseFingerprintLock($fingerprint);

                return response()->json([
                    'status' => false,
                    'message' => 'Failed to collect fee: ' . $e->getMessage()
                ], 500);
            }
        }
    }

    private function getFeeStructureId($studentId)
    {
        $assignment = StudentFeeAssignment::where('student_id', $studentId)
            ->latest()
            ->first();
            
        return $assignment ? $assignment->fee_structure_id : null;
    }

    private function getPendingFeesCount()
    {
        return FeeCollection::count();
    }

    public function getReceipt($id)
    {
        $feeCollection = FeeCollection::withTrashed()->with([
            'student.schoolClass',
            'feeStructure',
            'feeCollectionItems.feeType',
            'collectedBy'
        ])->findOrFail($id);

        return view('admin.fees.receipt', compact('feeCollection'));
    }
    
    public function show($student_id)
    {
        $student = Student::withTrashed()->with('schoolClass')->findOrFail($student_id);

        $studentFeeAssignment = $student->feeAssignments()->latest()->first();
        $feeStructure = $studentFeeAssignment ? $studentFeeAssignment->feeStructure : null;

        if (!$feeStructure) {
            $schoolClass = $student->resolveCanonicalSchoolClass();
            $className = $schoolClass ? $schoolClass->name : (is_string($student->class) ? $student->class : '');
            $feeStructure = FeeStructure::where('class_name', $className)
                ->active()
                ->first();
        }

        $feeCollections = $student->feeCollections()->with('feeCollectionItems.feeType')->latest()->get();

        return view('admin.fees.student-dashboard', compact(
            'student',
            'feeStructure',
            'feeCollections'
        ));
    }

    public function getReceiptPdf($id)
    {
        $feeCollection = FeeCollection::withTrashed()->with([
            'student',
            'feeStructure',
            'feeCollectionItems.feeType',
            'collectedBy'
        ])->findOrFail($id);

        $pdf = \PDF::loadView('admin.fees.receipt-pdf', compact('feeCollection'));
        return $pdf->download('receipt-' . $feeCollection->receipt_no . '.pdf');
    }

    public function createCollectionForm($studentId)
    {
        $student = Student::withTrashed()->with(['schoolClass', 'feeAssignments.feeStructure.feeStructureItems.feeType'])->findOrFail($studentId);
        
        $studentFeeAssignment = $student->feeAssignments()->latest()->first();
        $feeStructure = $studentFeeAssignment ? $studentFeeAssignment->feeStructure : null;

        if (!$feeStructure) {
            $schoolClass = $student->resolveCanonicalSchoolClass();
            $className = $schoolClass ? $schoolClass->name : (is_string($student->class) ? $student->class : '');
            $feeStructure = FeeStructure::where('class_name', $className)
                ->active()
                ->first();
        }

        if (!$feeStructure) {
            return redirect()->back()->with('error', 'No fee structure assigned to this class.');
        }

        // Get already collected/paid fee types for the student under this structure
        $paidFeeTypeIds = \App\Models\FeeCollectionItem::whereHas('feeCollection', function ($query) use ($student, $feeStructure) {
            $query->where('student_id', $student->id)
                  ->where('fee_structure_id', $feeStructure->id);
        })->pluck('fee_type_id')->toArray();

        $feeTypes = $feeStructure->feeStructureItems->filter(function ($item) use ($paidFeeTypeIds) {
            $freq = strtolower($item->billing_frequency ?? 'monthly');
            // If the item is yearly / session-wise / exam-wise and already paid, exclude it from display
            if (in_array($freq, ['yearly', 'session_wise_admission', 'exam_wise']) && in_array($item->fee_type_id, $paidFeeTypeIds)) {
                return false;
            }
            return true;
        })->map(function ($item) {
            return $item->feeType;
        });

        // 1. Calculate Ledger Outstanding
        $outstandingDues = 0.00;
        try {
            $outstandingDues = \App\Services\LedgerService::getOutstandingBalance($student->id);
        } catch (\Exception $e) {
            // Ignore missing table in tests
        }

        // 2. Fetch Transport details if active (decoupled from pushing to academic feeTypes)
        $transportFee = 0.00;
        try {
            $transportAssignment = \App\Models\StudentTransport::where('student_id', $student->id)->first();
            if ($transportAssignment) {
                if ($transportAssignment->stop && $transportAssignment->stop->fare > 0) {
                    $transportFee = $transportAssignment->stop->fare;
                } elseif ($transportAssignment->route) {
                    $transportFee = $transportAssignment->route->monthly_fare;
                }
            }
        } catch (\Exception $e) {
            // Ignore missing table in tests
        }

        // 3. Calculate eligible discounts
        $suggestedDiscount = 0.00;
        $discountDetails = '';
        try {
            $baseItemsForDiscount = [];
            foreach ($feeStructure->feeStructureItems as $item) {
                $baseItemsForDiscount[] = [
                    'fee_type_id' => $item->fee_type_id,
                    'amount' => $item->amount
                ];
            }

            $discountEngine = new \App\Services\DiscountEngineService();
            $currentMonthName = \Carbon\Carbon::now()->format('F');
            $appliedDiscounts = $discountEngine->calculateDiscounts(
                $student,
                $currentMonthName,
                $feeStructure->academic_year ?? '2026-27',
                $baseItemsForDiscount
            );

            if (!empty($appliedDiscounts)) {
                foreach ($appliedDiscounts as $d) {
                    $suggestedDiscount += $d['amount'];
                    $discountDetails .= $d['rule_name'] . ' (₹' . $d['amount'] . ') ';
                }
            }
        } catch (\Exception $e) {
            // Ignore missing table in tests
        }

        $months = $this->generateMonths($feeStructure->frequency);
        $prependedMonths = [];
        try {
            $currentYear = $feeStructure->academic_year ?? '2026-27';
            $previousUnpaid = \App\Models\StudentFeeLedger::where('student_id', $student->id)
                ->where('unpaid_amount', '>', 0)
                ->whereNotNull('academic_year')
                ->where('academic_year', '!=', $currentYear)
                ->orderBy('date', 'asc')
                ->get();

            foreach ($previousUnpaid as $entry) {
                $monthName = null;
                if (preg_match('/-\s*([A-Za-z0-9\s]+)$/', $entry->description, $matches)) {
                    $monthName = trim($matches[1]);
                }
                if ($monthName) {
                    $formattedMonth = "{$monthName} ({$entry->academic_year})";
                    if (!in_array($formattedMonth, $prependedMonths)) {
                $prependedMonths[] = $formattedMonth;
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }
        $months = array_merge($prependedMonths, $months);
        
        // Fetch outstanding invoices sorted by PaymentAllocationEngine policies
        $outstandingInvoices = \App\Services\PaymentAllocationEngine::getPrioritizedDebits($student->id);

        $paymentModes = ['cash' => 'Cash', 'upi' => 'UPI', 'bank' => 'Bank Transfer', 'card' => 'Card', 'online' => 'Online'];

        return view('admin.fees.collect-form', compact(
            'student', 
            'feeStructure', 
            'feeTypes', 
            'months', 
            'paymentModes',
            'outstandingDues',
            'transportFee',
            'suggestedDiscount',
            'discountDetails',
            'outstandingInvoices'
        ));
    }


    public function store(Request $request)
    {
        return $this->executeCounterCollection($request);
    }

    public function processCollection(Request $request)
    {
        return $this->executeCounterCollection($request);
    }

    private function executeCounterCollection(Request $request)
    {
        if (!$request->has('total_amount') && $request->has('amount')) {
            $amounts = is_array($request->amount) ? $request->amount : [];
            $totalAmount = array_sum(array_map('floatval', $amounts));
            $request->merge(['total_amount' => $totalAmount]);
        }

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'total_amount' => 'required|numeric|min:0.01',
            'payment_mode' => 'required|in:' . implode(',', FeeCollection::PAYMENT_MODES),
            'payment_date' => 'nullable|date',
            'transaction_id' => [
                \Illuminate\Validation\Rule::requiredIf(function () use ($request) {
                    return in_array($request->payment_mode, ['bank', 'online']);
                }),
                'nullable',
                'string',
                'max:100'
            ],
            'remarks' => 'nullable|string|max:500',
            'submission_token' => 'nullable|string',
            'manual_allocation_override' => 'nullable|boolean',
            'manual_allocations' => 'nullable|array'
        ]);

        if (empty($validated['payment_date'])) {
            $validated['payment_date'] = now()->format('Y-m-d');
        }

        $collectorId = auth('web')->id() ?? auth()->id();
        if (!$collectorId) {
            abort(403, 'Authenticated admin user required for fee collection.');
        }

        // Generate fingerprint
        $fingerprint = $this->lockService->generateFingerprint($validated + $request->all(), $collectorId);
        $hasToken = !empty($validated['submission_token']);
        $token = $validated['submission_token'] ?? null;

        if ($hasToken && !$this->lockService->acquireTokenLock($token)) {
            return redirect()->back()->with('error', 'This transaction is already being processed.');
        }

        if (!$this->lockService->acquireFingerprintLock($fingerprint)) {
            if ($hasToken) {
                $this->lockService->releaseTokenLock($token);
            }
            return redirect()->back()->with('error', 'This transaction is already being processed.');
        }

        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                DB::beginTransaction();

                $student = Student::findOrFail($validated['student_id']);
                $studentFeeAssignment = $student->feeAssignments()->latest()->first();
                $feeStructure = $studentFeeAssignment ? $studentFeeAssignment->feeStructure : null;

                if (!$feeStructure) {
                    $schoolClass = $student->resolveCanonicalSchoolClass();
                    $className = $schoolClass ? $schoolClass->name : (is_string($student->class) ? $student->class : '');
                    $feeStructure = FeeStructure::where('class_name', $className)
                        ->active()
                        ->first();
                }

                if (!$feeStructure) {
                    throw new \Exception('No fee structure found for this student\'s class');
                }

                $totalAmount = (float) $validated['total_amount'];
                $isOverride = $request->boolean('manual_allocation_override');
                
                $fallbackFeeType = \App\Models\FeeType::where('name', 'LIKE', '%Tuition%')->first() 
                    ?? \App\Models\FeeType::first();
                $fallbackFeeTypeId = $fallbackFeeType ? $fallbackFeeType->id : 1;

                $allocatedItems = [];
                $advanceAmount = 0.00;

                if ($isOverride) {
                    $manualMap = $request->input('manual_allocations', []);
                    $result = \App\Services\PaymentAllocationEngine::validateManualAllocation($student->id, $totalAmount, $manualMap);
                    
                    if (!$result['is_valid']) {
                        throw new \Exception(implode(' ', $result['errors']));
                    }

                    $allocatedItems = $result['allocations'];
                    $advanceAmount = $result['advance'];
                } else {
                    $result = \App\Services\PaymentAllocationEngine::autoAllocate($student->id, $totalAmount);
                    $allocatedItems = $result['allocations'];
                    $advanceAmount = $result['advance'];
                }

                // Group allocations by fee_type_id for FeeCollectionItems
                $allocations = [];
                $paidMonths = [];

                foreach ($allocatedItems as $allocated) {
                    $debit = $allocated['debit'];
                    $amount = $allocated['amount'];

                    $feeTypeId = $debit->fee_type_id ?? $fallbackFeeTypeId;
                    if (!isset($allocations[$feeTypeId])) {
                        $allocations[$feeTypeId] = 0.00;
                    }
                    $allocations[$feeTypeId] += $amount;

                    // Try to extract month name from invoice description
                    $monthName = null;
                    if (preg_match('/-\s*([A-Za-z]+)/', $debit->description, $matches)) {
                        $monthName = trim($matches[1]);
                    }
                    if ($monthName && in_array(strtolower($monthName), ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'])) {
                        if (!in_array($monthName, $paidMonths)) {
                            $paidMonths[] = $monthName;
                        }
                    }
                }

                if ($advanceAmount > 0) {
                    if (!isset($allocations[$fallbackFeeTypeId])) {
                        $allocations[$fallbackFeeTypeId] = 0.00;
                    }
                    $allocations[$fallbackFeeTypeId] += $advanceAmount;
                }

                // Determine fee month label
                if (!empty($paidMonths)) {
                    $feeMonth = implode(', ', $paidMonths);
                } else {
                    $feeMonth = \Carbon\Carbon::parse($validated['payment_date'])->format('F');
                }

                $receiptNo = FeeCollection::generateReceiptNumber();
                if ($attempt > 1) {
                    $receiptNo .= '-' . rand(1000, 9999);
                }

                // For manual override allocations, set the allocations to register in LedgerService
                if ($isOverride) {
                    \App\Services\LedgerService::$manualAllocationsToRegister = $allocatedItems;
                }

                // Create FeeCollection record
                $feeCollection = FeeCollection::create([
                    'receipt_no' => $receiptNo,
                    'student_id' => $student->id,
                    'fee_structure_id' => $feeStructure->id,
                    'total_amount' => $totalAmount,
                    'discount' => 0.00,
                    'late_fine' => 0.00,
                    'final_amount' => $totalAmount,
                    'payment_date' => $validated['payment_date'],
                    'payment_mode' => $validated['payment_mode'],
                    'transaction_id' => $validated['transaction_id'] ?? null,
                    'remarks' => $validated['remarks'] ?? ($isOverride ? 'Counter Collection (Manual Override)' : 'Counter Collection'),
                    'collected_by' => $collectorId,
                    'fee_month' => $feeMonth
                ]);

                // Create FeeCollectionItem records
                foreach ($allocations as $feeTypeId => $amount) {
                    if ($amount > 0) {
                        FeeCollectionItem::create([
                            'fee_collection_id' => $feeCollection->id,
                            'fee_type_id' => $feeTypeId,
                            'amount' => $amount
                        ]);
                    }
                }

                // Create audit log for detailed payment allocation tracking
                $auditDetails = [
                    'student_id' => $student->id,
                    'receipt_no' => $receiptNo,
                    'payment_amount' => $totalAmount,
                    'allocation_type' => $isOverride ? 'manual_override' : 'auto_allocation',
                    'allocations' => collect($allocatedItems)->map(function ($item) {
                        return [
                            'ledger_id' => $item['debit']->id,
                            'description' => $item['debit']->description,
                            'allocated_amount' => $item['amount']
                        ];
                    })->values()->toArray(),
                    'advance_credited' => $advanceAmount
                ];

                \App\Models\AuditLog::create([
                    'user_type' => 'App\Models\User',
                    'user_id' => $collectorId,
                    'model_type' => 'App\Models\FeeCollection',
                    'model_id' => $feeCollection->id,
                    'field_name' => 'payment_allocations',
                    'old_value' => null,
                    'new_value' => json_encode($auditDetails),
                    'action' => 'payment_allocation',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'performed_at' => now()
                ]);

                DB::commit();

                return redirect()->route('admin.fees.receipt', ['id' => $feeCollection->id, 'print_immediately' => '1'])
                    ->with('success', 'Fee collected successfully! Receipt generated: ' . $feeCollection->receipt_no);

            } catch (\Illuminate\Database\QueryException $e) {
                DB::rollback();
                \App\Services\LedgerService::$manualAllocationsToRegister = null;
                $isUniqueViolation = false;
                if ($e->getCode() == 23000 || strpos($e->getMessage(), 'unique constraint') !== false || strpos($e->getMessage(), 'UNIQUE') !== false) {
                    $isUniqueViolation = true;
                }
                
                if ($isUniqueViolation && $attempt < $maxAttempts) {
                    continue;
                }

                if ($hasToken) {
                    $this->lockService->releaseTokenLock($token);
                }
                $this->lockService->releaseFingerprintLock($fingerprint);

                $errorMessage = $isUniqueViolation 
                    ? 'Failed to collect fee due to receipt number collision. Please try again.' 
                    : 'Failed to collect fee: ' . $e->getMessage();

                return redirect()->back()->with('error', $errorMessage);
            } catch (\Exception $e) {
                DB::rollback();
                \App\Services\LedgerService::$manualAllocationsToRegister = null;

                if ($hasToken) {
                    $this->lockService->releaseTokenLock($token);
                }
                $this->lockService->releaseFingerprintLock($fingerprint);

                return redirect()->back()->with('error', 'Failed to collect fee: ' . $e->getMessage());
            }
        }
    }

    public function receipt($id)
    {
        $feeCollection = FeeCollection::withTrashed()->with([
            'student',
            'feeStructure',
            'feeCollectionItems.feeType',
            'collectedBy'
        ])->findOrFail($id);

        return view('admin.fees.receipt', compact('feeCollection'));
    }

    public function reverseCollection(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        try {
            $collection = FeeCollection::withTrashed()->findOrFail($id);
            $studentId = $collection->student_id;

            if ($request->user() && ($request->user()->hasRole('clerk') || $request->user()->role === 'clerk')) {
                // Clerks cannot delete/revert directly. Submit request instead!
                \App\Models\FeeReversalRequest::create([
                    'fee_collection_id' => $collection->id,
                    'student_id' => $studentId,
                    'requested_by' => $request->user()->id,
                    'reason' => $request->reason,
                    'status' => 'pending'
                ]);

                return redirect()->route('admin.fees.student-dashboard', $studentId)
                    ->with('success', 'Fee reversal request submitted for Admin approval.');
            }

            // Otherwise, Admin/Accountant can reverse directly
            \App\Services\RefundService::reverseCollection($id, $request->reason);

            return redirect()->route('admin.fees.student-dashboard', $studentId)
                ->with('success', 'Fee collection reversed successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to reverse payment: ' . $e->getMessage());
        }
    }

    public function generateUpiQr(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'student_id' => 'required|exists:students,id'
        ]);

        $student = Student::findOrFail($request->student_id);

        $schoolUpiId = \App\Models\AdminConfiguration::get('fee', 'upi_vpa', '') ?: env('SCHOOL_UPI_ID', 'helpinghand@upi');
        $schoolName = \App\Models\AdminConfiguration::get('general', 'school_name', 'Helping Hand School');

        $result = \App\Services\UpiQrService::generate(
            $schoolUpiId,
            $schoolName,
            (float) $request->amount,
            "Fee Payment for " . $student->name,
            'CTR-' . $student->id . '-' . now()->format('YmdHis')
        );

        return response()->json([
            'status' => true,
            'qr_code' => $result['qr_code'],
            'upi_uri' => $result['upi_uri']
        ]);
    }

    private function hasPriorMonthOutstanding($studentId, $targetMonth, $targetAcademicYear)
    {
        $monthsSequence = ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];
        
        $quarterMap = [
            'Q1' => 'April',
            'Q2' => 'July',
            'Q3' => 'October',
            'Q4' => 'January',
            'Annual' => 'April'
        ];
        
        $cleanTargetMonth = $targetMonth;
        $cleanTargetYear = $targetAcademicYear;
        if (preg_match('/^([A-Za-z0-9\s]+)\s*\(([^)]+)\)$/', $targetMonth, $matches)) {
            $cleanTargetMonth = trim($matches[1]);
            $cleanTargetYear = trim($matches[2]);
        }
        
        if (isset($quarterMap[$cleanTargetMonth])) {
            $cleanTargetMonth = $quarterMap[$cleanTargetMonth];
        }
        
        try {
            $unpaidEntries = \App\Models\StudentFeeLedger::where('student_id', $studentId)
                ->where('unpaid_amount', '>', 0)
                ->whereNotNull('academic_year')
                ->get();
                
            foreach ($unpaidEntries as $entry) {
                $entryYear = $entry->academic_year;
                
                $entryMonth = null;
                if (preg_match('/-\s*([A-Za-z0-9\s]+)$/', $entry->description, $matches)) {
                    $entryMonth = trim($matches[1]);
                }
                
                if (!$entryMonth) {
                    continue;
                }
                
                if (isset($quarterMap[$entryMonth])) {
                    $entryMonth = $quarterMap[$entryMonth];
                }
                
                if ($entryYear < $cleanTargetYear) {
                    return true;
                }
                
                if ($entryYear === $cleanTargetYear) {
                    $targetIndex = array_search($cleanTargetMonth, $monthsSequence);
                    $entryIndex = array_search($entryMonth, $monthsSequence);
                    
                    if ($targetIndex !== false && $entryIndex !== false && $entryIndex < $targetIndex) {
                        return true;
                    }
                }
            }
        } catch (\Exception $e) {
            // Silence exceptions in tests
        }
        
        return false;
    }

    public function listReversalRequests()
    {
        $requests = \App\Models\FeeReversalRequest::with(['feeCollection', 'student', 'requester'])
            ->where('status', 'pending')
            ->paginate(15);
        return view('admin.fees.reversal-requests', compact('requests'));
    }

    public function approveReversal(Request $request, $id)
    {
        try {
            $reversalRequest = \App\Models\FeeReversalRequest::findOrFail($id);
            
            DB::transaction(function () use ($reversalRequest) {
                $reversalRequest->update([
                    'status' => 'approved',
                    'processed_by' => auth()->id(),
                    'reversal_date' => now()
                ]);
                
                $collection = \App\Models\FeeCollection::find($reversalRequest->fee_collection_id);
                if ($collection) {
                    $collection->update(['remarks' => 'reversed']);
                }

                \App\Services\RefundService::reverseCollection($reversalRequest->fee_collection_id, $reversalRequest->reason);
                
                if ($collection) {
                    \App\Services\LedgerService::rebuildRunningBalances($collection->student_id);
                }
            });

            return redirect()->back()->with('success', 'Fee collection reversal request approved and transaction reverted.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to approve reversal: ' . $e->getMessage());
        }
    }

    public function rejectReversal(Request $request, $id)
    {
        try {
            $reversalRequest = \App\Models\FeeReversalRequest::findOrFail($id);
            $reversalRequest->update([
                'status' => 'rejected',
                'processed_by' => auth()->id()
            ]);

            return redirect()->back()->with('success', 'Fee collection reversal request rejected.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to reject reversal: ' . $e->getMessage());
        }
    }

    /**
     * Display the Fee Demand Register page with paginated rows.
     */
    public function demandRegister(Request $request)
    {
        $classes = \App\Models\SchoolClass::active()->get();
        $sections = \App\Models\Section::all();
        $sessions = \App\Models\StudentFeeLedger::distinct()->whereNotNull('academic_year')->pluck('academic_year');
        $feeHeads = \App\Models\FeeType::active()->get();

        $query = $this->buildDemandRegisterQuery($request);
        $records = $query->paginate(50)->withQueryString();
        $totals = $this->calculateDemandRegisterTotals($request);

        return view('admin.fees.demand-register', compact(
            'records', 'totals', 'classes', 'sections', 'sessions', 'feeHeads'
        ));
    }

    /**
     * Export the Fee Demand Register in Excel (CSV stream), PDF, or Print formats.
     */
    public function exportDemandRegister(Request $request)
    {
        $format = $request->get('format', 'print');
        $query = $this->buildDemandRegisterQuery($request);
        $totals = $this->calculateDemandRegisterTotals($request);

        if ($format === 'pdf') {
            $records = $query->get();
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.fees.demand-register-pdf', compact('records', 'totals', 'request'));
            $pdf->setPaper('A4', 'landscape');
            return $pdf->download('fee-demand-register-' . now()->format('Y-m-d') . '.pdf');
        } elseif ($format === 'excel') {
            $records = $query->cursor();
            $fileName = 'fee-demand-register-' . now()->format('Y-m-d') . '.csv';
            
            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=$fileName",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $callback = function() use($records, $totals) {
                $file = fopen('php://output', 'w');
                
                // Excel UTF-8 BOM
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                
                fputcsv($file, [
                    'Admission No', 
                    'Student', 
                    'Class', 
                    'Section', 
                    'Fee Demand (INR)', 
                    'Discount (INR)', 
                    'Late Fee (INR)', 
                    'Collected (INR)', 
                    'Refund (INR)', 
                    'Outstanding (INR)'
                ]);

                foreach ($records as $row) {
                    fputcsv($file, [
                        $row->admission_no,
                        $row->student_name,
                        $row->class_name,
                        $row->section_name,
                        number_format($row->fee_demand, 2, '.', ''),
                        number_format($row->discount, 2, '.', ''),
                        number_format($row->late_fee, 2, '.', ''),
                        number_format($row->collected, 2, '.', ''),
                        number_format($row->refund, 2, '.', ''),
                        number_format($row->outstanding, 2, '.', '')
                    ]);
                }

                // Append Totals row
                fputcsv($file, []);
                fputcsv($file, [
                    'GRAND TOTALS',
                    '',
                    '',
                    '',
                    number_format($totals->total_demand ?? 0, 2, '.', ''),
                    number_format($totals->total_discount ?? 0, 2, '.', ''),
                    number_format($totals->total_late_fee ?? 0, 2, '.', ''),
                    number_format($totals->total_collected ?? 0, 2, '.', ''),
                    number_format($totals->total_refund ?? 0, 2, '.', ''),
                    number_format($totals->total_outstanding ?? 0, 2, '.', '')
                ]);

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } else {
            $records = $query->get();
            return view('admin.fees.demand-register-print', compact('records', 'totals', 'request'));
        }
    }

    /**
     * Build the conditional-aggregate query based on incoming filters.
     */
    private function buildDemandRegisterQuery(Request $request)
    {
        $query = \App\Models\Student::withTrashed()
            ->select('students.id', 'students.admission_no', 'students.name as student_name')
            ->selectRaw('school_classes.name as class_name')
            ->selectRaw('sections.name as section_name')
            ->leftJoin('school_classes', 'school_classes.id', '=', DB::raw('COALESCE(students.class_id, students.school_class_id)'))
            ->leftJoin('sections', 'sections.id', '=', 'students.section_id')
            ->leftJoin('student_fee_ledgers as ledger', 'ledger.student_id', '=', 'students.id');

        if ($request->filled('class_id')) {
            $query->where(function($q) use($request) {
                $q->where('students.class_id', $request->class_id)
                  ->orWhere('students.school_class_id', $request->class_id);
            });
        }
        if ($request->filled('section_id')) {
            $query->where('students.section_id', $request->section_id);
        }

        $ledgerFilterSql = '1=1';
        $bindings = [];

        if ($request->filled('session')) {
            $ledgerFilterSql .= ' AND ledger.academic_year = ?';
            $bindings[] = $request->session;
        }

        if ($request->filled('month')) {
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            if ($isSqlite) {
                $ledgerFilterSql .= ' AND strftime(\'%m\', ledger.date) = ?';
            } else {
                $ledgerFilterSql .= ' AND MONTH(ledger.date) = ?';
            }
            $monthVal = sprintf('%02d', $request->month);
            $bindings[] = $monthVal;
        }

        if ($request->filled('fee_head_id')) {
            $ledgerFilterSql .= ' AND ledger.fee_type_id = ?';
            $bindings[] = $request->fee_head_id;
        }

        // Conditional aggregates ensuring no duplicated allocation values
        $query->selectRaw("
            SUM(CASE WHEN ledger.debit > 0 AND ledger.reference_type != 'fee_refund' AND ledger.reference_type != 'fee_collection' AND {$ledgerFilterSql} THEN ledger.debit ELSE 0 END) as fee_demand,
            SUM(CASE WHEN ledger.credit > 0 AND ledger.description LIKE '%Discount%' AND {$ledgerFilterSql} THEN ledger.credit ELSE 0 END) as discount,
            SUM(CASE WHEN ledger.debit > 0 AND ledger.reference_type = 'fee_collection' AND {$ledgerFilterSql} THEN ledger.debit ELSE 0 END) as late_fee,
            SUM(CASE WHEN ledger.credit > 0 AND (ledger.description NOT LIKE '%Discount%' OR ledger.description IS NULL) AND {$ledgerFilterSql} THEN ledger.credit ELSE 0 END) as collected,
            SUM(CASE WHEN ledger.debit > 0 AND ledger.reference_type = 'fee_refund' AND {$ledgerFilterSql} THEN ledger.debit ELSE 0 END) as refund,
            SUM(CASE WHEN {$ledgerFilterSql} THEN ledger.debit ELSE 0 END) - SUM(CASE WHEN {$ledgerFilterSql} THEN ledger.credit ELSE 0 END) as outstanding
        ", array_merge($bindings, $bindings, $bindings, $bindings, $bindings, $bindings, $bindings));

        $query->groupBy('students.id', 'students.admission_no', 'students.name', 'school_classes.name', 'sections.name');

        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'paid') {
                $query->havingRaw('(SUM(CASE WHEN ' . $ledgerFilterSql . ' THEN ledger.debit ELSE 0 END) - SUM(CASE WHEN ' . $ledgerFilterSql . ' THEN ledger.credit ELSE 0 END)) <= 0', array_merge($bindings, $bindings));
            } elseif ($status === 'unpaid') {
                $query->havingRaw('(SUM(CASE WHEN ' . $ledgerFilterSql . ' THEN ledger.debit ELSE 0 END) - SUM(CASE WHEN ' . $ledgerFilterSql . ' THEN ledger.credit ELSE 0 END)) > 0 AND SUM(CASE WHEN ledger.credit > 0 AND (ledger.description NOT LIKE \'%Discount%\' OR ledger.description IS NULL) AND ' . $ledgerFilterSql . ' THEN ledger.credit ELSE 0 END) = 0', array_merge($bindings, $bindings, $bindings));
            } elseif ($status === 'partially_paid') {
                $query->havingRaw('(SUM(CASE WHEN ' . $ledgerFilterSql . ' THEN ledger.debit ELSE 0 END) - SUM(CASE WHEN ' . $ledgerFilterSql . ' THEN ledger.credit ELSE 0 END)) > 0 AND SUM(CASE WHEN ledger.credit > 0 AND (ledger.description NOT LIKE \'%Discount%\' OR ledger.description IS NULL) AND ' . $ledgerFilterSql . ' THEN ledger.credit ELSE 0 END) > 0', array_merge($bindings, $bindings, $bindings));
            } elseif ($status === 'overpaid') {
                $query->havingRaw('(SUM(CASE WHEN ' . $ledgerFilterSql . ' THEN ledger.debit ELSE 0 END) - SUM(CASE WHEN ' . $ledgerFilterSql . ' THEN ledger.credit ELSE 0 END)) < 0', array_merge($bindings, $bindings));
            }
        }

        return $query;
    }

    /**
     * Compute grand totals over the filtered subquery dataset.
     */
    private function calculateDemandRegisterTotals(Request $request)
    {
        $subQuery = $this->buildDemandRegisterQuery($request);
        
        $totals = DB::table(DB::raw("({$subQuery->toSql()}) as sub"))
            ->mergeBindings($subQuery->getQuery())
            ->selectRaw('
                SUM(fee_demand) as total_demand,
                SUM(discount) as total_discount,
                SUM(late_fee) as total_late_fee,
                SUM(collected) as total_collected,
                SUM(refund) as total_refund,
                SUM(outstanding) as total_outstanding
            ')
            ->first();

        return $totals;
    }

    /**
     * Display the Daily Collection Register dashboard with dynamic grouping options.
     */
    public function collectionRegister(Request $request)
    {
        $cashiers = \App\Models\User::whereHas('roles', function($q) {
            $q->whereIn('name', ['admin', 'accountant']);
        })->orWhere('role', 'admin')->orWhere('role', 'accountant')->get();

        $groupBy = $request->get('group_by', 'date');
        $query = $this->buildCollectionRegisterQuery($request, $groupBy);
        $records = $query->paginate(50)->withQueryString();
        $totals = $this->calculateCollectionRegisterTotals($request, $groupBy);

        return view('admin.fees.collection-register', compact(
            'records', 'totals', 'cashiers', 'groupBy'
        ));
    }

    /**
     * Export the Daily Collection Register in PDF, Excel/CSV, or Print layouts.
     */
    public function exportCollectionRegister(Request $request)
    {
        $format = $request->get('format', 'print');
        $groupBy = $request->get('group_by', 'date');
        $query = $this->buildCollectionRegisterQuery($request, $groupBy);
        $totals = $this->calculateCollectionRegisterTotals($request, $groupBy);

        if ($format === 'pdf') {
            $records = $query->get();
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.fees.collection-register-pdf', compact('records', 'totals', 'groupBy', 'request'));
            $pdf->setPaper('A4', 'landscape');
            return $pdf->download('daily-collection-register-' . now()->format('Y-m-d') . '.pdf');
        } elseif ($format === 'excel') {
            $records = $query->cursor();
            $fileName = 'daily-collection-register-' . now()->format('Y-m-d') . '.csv';
            
            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=$fileName",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $callback = function() use($records, $totals, $groupBy) {
                $file = fopen('php://output', 'w');
                
                // BOM
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                
                if ($groupBy === 'cashier') {
                    fputcsv($file, ['Cashier', 'Cash', 'UPI', 'Bank', 'Cheque', 'Online', 'Refund', 'Cancelled', 'Net Collection']);
                    foreach ($records as $row) {
                        fputcsv($file, [
                            $row->cashier_name,
                            number_format($row->cash, 2, '.', ''),
                            number_format($row->upi, 2, '.', ''),
                            number_format($row->bank, 2, '.', ''),
                            number_format($row->cheque, 2, '.', ''),
                            number_format($row->online, 2, '.', ''),
                            number_format($row->refund, 2, '.', ''),
                            number_format($row->cancelled, 2, '.', ''),
                            number_format($row->net_collection, 2, '.', '')
                        ]);
                    }
                } elseif ($groupBy === 'date') {
                    fputcsv($file, ['Date', 'Cash', 'UPI', 'Bank', 'Cheque', 'Online', 'Refund', 'Cancelled', 'Net Collection']);
                    foreach ($records as $row) {
                        fputcsv($file, [
                            $row->group_date,
                            number_format($row->cash, 2, '.', ''),
                            number_format($row->upi, 2, '.', ''),
                            number_format($row->bank, 2, '.', ''),
                            number_format($row->cheque, 2, '.', ''),
                            number_format($row->online, 2, '.', ''),
                            number_format($row->refund, 2, '.', ''),
                            number_format($row->cancelled, 2, '.', ''),
                            number_format($row->net_collection, 2, '.', '')
                        ]);
                    }
                } elseif ($groupBy === 'payment_mode') {
                    fputcsv($file, ['Payment Mode', 'Transaction Count', 'Total Collection']);
                    foreach ($records as $row) {
                        fputcsv($file, [
                            $row->payment_mode,
                            $row->tx_count,
                            number_format($row->cash_total, 2, '.', '')
                        ]);
                    }
                } else {
                    fputcsv($file, ['Receipt No', 'Date', 'Student', 'Cashier', 'Payment Mode', 'Amount', 'Status']);
                    foreach ($records as $row) {
                        fputcsv($file, [
                            $row->receipt_no,
                            $row->payment_date,
                            $row->student_name,
                            $row->cashier_name,
                            $row->payment_mode,
                            number_format($row->final_amount, 2, '.', ''),
                            $row->deleted_at ? 'Cancelled' : 'Active'
                        ]);
                    }
                }

                if ($groupBy === 'cashier' || $groupBy === 'date') {
                    fputcsv($file, []);
                    fputcsv($file, [
                        'GRAND TOTALS',
                        number_format($totals->total_cash, 2, '.', ''),
                        number_format($totals->total_upi, 2, '.', ''),
                        number_format($totals->total_bank, 2, '.', ''),
                        number_format($totals->total_cheque, 2, '.', ''),
                        number_format($totals->total_online, 2, '.', ''),
                        number_format($totals->total_refund, 2, '.', ''),
                        number_format($totals->total_cancelled, 2, '.', ''),
                        number_format($totals->net_collection, 2, '.', '')
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } else {
            $records = $query->get();
            return view('admin.fees.collection-register-print', compact('records', 'totals', 'groupBy', 'request'));
        }
    }

    /**
     * Build the Daily Collection Register database-level aggregation queries.
     */
    private function buildCollectionRegisterQuery(Request $request, string $groupBy)
    {
        $startDate = $request->get('start_date', now()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        if ($groupBy === 'cashier') {
            $query = DB::table('users')
                ->select('users.id as cashier_id', 'users.name as cashier_name');

            $activeSub = DB::table('fee_collections')
                ->select('collected_by')
                ->selectRaw("SUM(CASE WHEN payment_mode = 'cash' THEN final_amount ELSE 0 END) as cash_total")
                ->selectRaw("SUM(CASE WHEN payment_mode = 'upi' THEN final_amount ELSE 0 END) as upi_total")
                ->selectRaw("SUM(CASE WHEN payment_mode = 'bank' THEN final_amount ELSE 0 END) as bank_total")
                ->selectRaw("SUM(CASE WHEN payment_mode = 'cheque' THEN final_amount ELSE 0 END) as cheque_total")
                ->selectRaw("SUM(CASE WHEN payment_mode = 'online' THEN final_amount ELSE 0 END) as online_total")
                ->whereNull('deleted_at')
                ->whereDate('payment_date', '>=', $startDate)
                ->whereDate('payment_date', '<=', $endDate)
                ->groupBy('collected_by');

            $query->leftJoinSub($activeSub, 'active_fc', 'active_fc.collected_by', '=', 'users.id');

            $cancelledSub = DB::table('fee_collections')
                ->select('collected_by')
                ->selectRaw("SUM(final_amount) as cancelled_total")
                ->whereNotNull('deleted_at')
                ->whereDate('payment_date', '>=', $startDate)
                ->whereDate('payment_date', '<=', $endDate)
                ->groupBy('collected_by');

            $query->leftJoinSub($cancelledSub, 'cancelled_fc', 'cancelled_fc.collected_by', '=', 'users.id');

            $refundSub = DB::table('fee_refunds')
                ->select('processed_by')
                ->selectRaw("SUM(amount) as refund_total")
                ->where('type', 'refund')
                ->whereBetween('processed_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->groupBy('processed_by');

            $query->leftJoinSub($refundSub, 'refund_fr', 'refund_fr.processed_by', '=', 'users.id');

            $query->selectRaw('
                COALESCE(active_fc.cash_total, 0) as cash,
                COALESCE(active_fc.upi_total, 0) as upi,
                COALESCE(active_fc.bank_total, 0) as bank,
                COALESCE(active_fc.cheque_total, 0) as cheque,
                COALESCE(active_fc.online_total, 0) as online,
                COALESCE(refund_fr.refund_total, 0) as refund,
                COALESCE(cancelled_fc.cancelled_total, 0) as cancelled,
                (COALESCE(active_fc.cash_total, 0) + COALESCE(active_fc.upi_total, 0) + COALESCE(active_fc.bank_total, 0) + COALESCE(active_fc.cheque_total, 0) + COALESCE(active_fc.online_total, 0) - COALESCE(refund_fr.refund_total, 0)) as net_collection
            ');

            $query->where(function($q) {
                $q->whereNotNull('active_fc.collected_by')
                  ->orWhereNotNull('cancelled_fc.collected_by')
                  ->orWhereNotNull('refund_fr.processed_by');
            });

        } elseif ($groupBy === 'date') {
            $query = DB::table('fee_collections')
                ->select('fee_collections.payment_date as group_date')
                ->whereNull('fee_collections.deleted_at')
                ->whereDate('fee_collections.payment_date', '>=', $startDate)
                ->whereDate('fee_collections.payment_date', '<=', $endDate);

            if ($request->filled('cashier_id')) {
                $query->where('fee_collections.collected_by', $request->cashier_id);
            }
            $query->distinct();

            $activeSub = DB::table('fee_collections')
                ->select('payment_date')
                ->selectRaw("SUM(CASE WHEN payment_mode = 'cash' THEN final_amount ELSE 0 END) as cash_total")
                ->selectRaw("SUM(CASE WHEN payment_mode = 'upi' THEN final_amount ELSE 0 END) as upi_total")
                ->selectRaw("SUM(CASE WHEN payment_mode = 'bank' THEN final_amount ELSE 0 END) as bank_total")
                ->selectRaw("SUM(CASE WHEN payment_mode = 'cheque' THEN final_amount ELSE 0 END) as cheque_total")
                ->selectRaw("SUM(CASE WHEN payment_mode = 'online' THEN final_amount ELSE 0 END) as online_total")
                ->whereNull('deleted_at');
            if ($request->filled('cashier_id')) {
                $activeSub->where('collected_by', $request->cashier_id);
            }
            $activeSub->groupBy('payment_date');

            $query->leftJoinSub($activeSub, 'active_fc', 'active_fc.payment_date', '=', 'fee_collections.payment_date');

            $cancelledSub = DB::table('fee_collections')
                ->select('payment_date')
                ->selectRaw("SUM(final_amount) as cancelled_total")
                ->whereNotNull('deleted_at');
            if ($request->filled('cashier_id')) {
                $cancelledSub->where('collected_by', $request->cashier_id);
            }
            $cancelledSub->groupBy('payment_date');

            $query->leftJoinSub($cancelledSub, 'cancelled_fc', 'cancelled_fc.payment_date', '=', 'fee_collections.payment_date');

            $refundSub = DB::table('fee_refunds')
                ->selectRaw('DATE(processed_at) as processed_date')
                ->selectRaw("SUM(amount) as refund_total")
                ->where('type', 'refund');
            if ($request->filled('cashier_id')) {
                $refundSub->where('processed_by', $request->cashier_id);
            }
            $refundSub->groupBy(DB::raw('DATE(processed_at)'));

            $query->leftJoinSub($refundSub, 'refund_fr', 'refund_fr.processed_date', '=', 'fee_collections.payment_date');

            $query->selectRaw('
                COALESCE(active_fc.cash_total, 0) as cash,
                COALESCE(active_fc.upi_total, 0) as upi,
                COALESCE(active_fc.bank_total, 0) as bank,
                COALESCE(active_fc.cheque_total, 0) as cheque,
                COALESCE(active_fc.online_total, 0) as online,
                COALESCE(refund_fr.refund_total, 0) as refund,
                COALESCE(cancelled_fc.cancelled_total, 0) as cancelled,
                (COALESCE(active_fc.cash_total, 0) + COALESCE(active_fc.upi_total, 0) + COALESCE(active_fc.bank_total, 0) + COALESCE(active_fc.cheque_total, 0) + COALESCE(active_fc.online_total, 0) - COALESCE(refund_fr.refund_total, 0)) as net_collection
            ');

        } elseif ($groupBy === 'payment_mode') {
            $query = DB::table('fee_collections')
                ->select('payment_mode')
                ->selectRaw('COUNT(*) as tx_count')
                ->selectRaw('SUM(final_amount) as cash_total')
                ->whereNull('deleted_at')
                ->whereDate('payment_date', '>=', $startDate)
                ->whereDate('payment_date', '<=', $endDate);

            if ($request->filled('cashier_id')) {
                $query->where('collected_by', $request->cashier_id);
            }
            $query->groupBy('payment_mode');

        } else {
            $query = DB::table('fee_collections')
                ->select('fee_collections.id', 'fee_collections.receipt_no', 'fee_collections.payment_date', 'fee_collections.payment_mode', 'fee_collections.final_amount', 'fee_collections.deleted_at')
                ->selectRaw('students.name as student_name')
                ->selectRaw('users.name as cashier_name')
                ->leftJoin('students', 'students.id', '=', 'fee_collections.student_id')
                ->leftJoin('users', 'users.id', '=', 'fee_collections.collected_by')
                ->whereDate('payment_date', '>=', $startDate)
                ->whereDate('payment_date', '<=', $endDate)
                ->withTrashed();

            if ($request->filled('cashier_id')) {
                $query->where('fee_collections.collected_by', $request->cashier_id);
            }
            if ($request->filled('payment_mode')) {
                $query->where('fee_collections.payment_mode', $request->payment_mode);
            }

            $query->orderBy('fee_collections.receipt_no', 'asc');
        }

        return $query;
    }

    /**
     * Compute register grand totals across active/cancelled transactions and refunds.
     */
    private function calculateCollectionRegisterTotals(Request $request, string $groupBy)
    {
        $startDate = $request->get('start_date', now()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $activeSum = DB::table('fee_collections')
            ->whereNull('deleted_at')
            ->whereDate('payment_date', '>=', $startDate)
            ->whereDate('payment_date', '<=', $endDate);
        if ($request->filled('cashier_id')) {
            $activeSum->where('collected_by', $request->cashier_id);
        }
        if ($groupBy === 'payment_mode' && $request->filled('payment_mode')) {
            $activeSum->where('payment_mode', $request->payment_mode);
        }
        
        $activeTotals = $activeSum->selectRaw("
            SUM(CASE WHEN payment_mode = 'cash' THEN final_amount ELSE 0 END) as total_cash,
            SUM(CASE WHEN payment_mode = 'upi' THEN final_amount ELSE 0 END) as total_upi,
            SUM(CASE WHEN payment_mode = 'bank' THEN final_amount ELSE 0 END) as total_bank,
            SUM(CASE WHEN payment_mode = 'cheque' THEN final_amount ELSE 0 END) as total_cheque,
            SUM(CASE WHEN payment_mode = 'online' THEN final_amount ELSE 0 END) as total_online
        ")->first();

        $cancelledSum = DB::table('fee_collections')
            ->whereNotNull('deleted_at')
            ->whereDate('payment_date', '>=', $startDate)
            ->whereDate('payment_date', '<=', $endDate);
        if ($request->filled('cashier_id')) {
            $cancelledSum->where('collected_by', $request->cashier_id);
        }
        $cancelledTotal = $cancelledSum->sum('final_amount');

        $refundSum = DB::table('fee_refunds')
            ->where('type', 'refund')
            ->whereBetween('processed_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        if ($request->filled('cashier_id')) {
            $refundSum->where('processed_by', $request->cashier_id);
        }
        $refundTotal = $refundSum->sum('amount');

        $totalCash = (float)($activeTotals->total_cash ?? 0);
        $totalUpi = (float)($activeTotals->total_upi ?? 0);
        $totalBank = (float)($activeTotals->total_bank ?? 0);
        $totalCheque = (float)($activeTotals->total_cheque ?? 0);
        $totalOnline = (float)($activeTotals->total_online ?? 0);

        $net = $totalCash + $totalUpi + $totalBank + $totalCheque + $totalOnline - $refundTotal;

        return (object)[
            'total_cash' => $totalCash,
            'total_upi' => $totalUpi,
            'total_bank' => $totalBank,
            'total_cheque' => $totalCheque,
            'total_online' => $totalOnline,
            'total_refund' => $refundTotal,
            'total_cancelled' => $cancelledTotal,
            'net_collection' => $net
        ];
    }
}
