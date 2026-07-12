<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\StudentFeeAssignment;
use App\Models\FeeCollection;
use App\Models\FeeCollectionItem;
use App\Models\Student;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\FeePaymentLockService;

class InstallmentFeeController extends Controller
{
    protected $lockService;

    public function __construct(FeePaymentLockService $lockService)
    {
        $this->middleware('auth');
        $this->lockService = $lockService;
    }

    /**
     * Display installment fee management dashboard
     */
    public function index()
    {
        $feeStructures = FeeStructure::with(['feeStructureItems'])->get();
        $classes = SchoolClass::all();
        
        return view('admin.installment-fees.index', compact('feeStructures', 'classes'));
    }
    public function create()
    {
        $classes = SchoolClass::all();
        return view('admin.installment-fees.create', compact('classes'));
    }

    /**
     * Store new installment fee structure
     */
    public function store(Request $request)
    {
        $request->validate([
            'class_name' => 'required|string',
            'academic_year' => 'required|string',
            'frequency' => 'required|in:monthly,quarterly,yearly,installment',
            'installment_count' => 'required_if:frequency,installment|integer|min:1|max:12',
            'installment_frequency' => 'required_if:frequency,installment|in:monthly,bimonthly,quarterly',
            'fee_items' => 'required|array',
            'fee_items.*.name' => 'required|string',
            'fee_items.*.amount' => 'required|numeric|min:0',
        ]);

        $userId = auth('web')->id();

        if (!$userId) {
            abort(403, 'Authenticated admin user required.');
        }

        DB::transaction(function () use ($request, $userId) {
            $feeStructure = FeeStructure::create([
                'class_name' => $request->class_name,
                'academic_year' => $request->academic_year,
                'frequency' => $request->frequency,
                'installment_count' => $request->installment_count ?? 1,
                'installment_frequency' => $request->installment_frequency ?? 'monthly',
                'status' => 'active',
                'created_by' => $userId
            ]);

            // Create fee structure items
            foreach ($request->fee_items as $item) {
                FeeStructureItem::create([
                    'fee_structure_id' => $feeStructure->id,
                    'name' => $item['name'],
                    'amount' => $item['amount'],
                    'due_day' => $item['due_day'] ?? 15 // Default due day
                ]);
            }
        });

        return redirect()->route('admin.installment-fees.index')
            ->with('success', 'Installment fee structure created successfully!');
    }

    /**
     * Edit installment fee structure
     */
    public function edit($id)
    {
        $feeStructure = FeeStructure::with(['feeStructureItems'])->findOrFail($id);
        $classes = SchoolClass::all();
        
        return view('admin.installment-fees.edit', compact('feeStructure', 'classes'));
    }

    /**
     * Update installment fee structure
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'class_name' => 'required|string',
            'academic_year' => 'required|string',
            'frequency' => 'required|in:monthly,quarterly,yearly,installment',
            'installment_count' => 'required_if:frequency,installment|integer|min:1|max:12',
            'installment_frequency' => 'required_if:frequency,installment|in:monthly,bimonthly,quarterly',
            'fee_items' => 'required|array',
            'fee_items.*.name' => 'required|string',
            'fee_items.*.amount' => 'required|numeric|min:0',
        ]);

        $feeStructure = FeeStructure::findOrFail($id);

        DB::transaction(function () use ($request, $feeStructure) {
            $feeStructure->update([
                'class_name' => $request->class_name,
                'academic_year' => $request->academic_year,
                'frequency' => $request->frequency,
                'installment_count' => $request->installment_count ?? 1,
                'installment_frequency' => $request->installment_frequency ?? 'monthly',
            ]);

            // Update fee structure items
            // First delete existing items
            $feeStructure->feeStructureItems()->delete();

            // Then create new items
            foreach ($request->fee_items as $item) {
                FeeStructureItem::create([
                    'fee_structure_id' => $feeStructure->id,
                    'name' => $item['name'],
                    'amount' => $item['amount'],
                    'due_day' => $item['due_day'] ?? 15
                ]);
            }
        });

        return redirect()->route('admin.installment-fees.index')
            ->with('success', 'Installment fee structure updated successfully!');
    }

    /**
     * Assign fee structure to students (installment schedule)
     */
    public function assignToClass(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'fee_structure_id' => 'required|exists:fee_structures,id'
        ]);

        $classId = $request->class_id;
        $feeStructureId = $request->fee_structure_id;
        $userId = auth('web')->id();

        if (!$userId) {
            abort(403, 'Authenticated admin user required.');
        }

        // Get all students in the class -- OR both columns, same as
        // FeeStructureController::autoAssignToStudents(); school_class_id
        // alone silently missed any student whose class_id was set but
        // school_class_id wasn't.
        $students = Student::where(function ($q) use ($classId) {
            $q->where('class_id', $classId)->orWhere('school_class_id', $classId);
        })->get();

        $assignedCount = DB::transaction(function () use ($students, $feeStructureId, $userId) {
            $assignedCount = 0;

            foreach ($students as $student) {
                // Check if already assigned
                $existingAssignment = StudentFeeAssignment::where('student_id', $student->id)
                    ->where('fee_structure_id', $feeStructureId)
                    ->first();

                if (!$existingAssignment) {
                    StudentFeeAssignment::create([
                        'student_id' => $student->id,
                        'fee_structure_id' => $feeStructureId,
                        'assigned_by' => $userId,
                        'assigned_date' => now()
                    ]);
                    $assignedCount++;
                }
            }

            return $assignedCount;
        });

        return redirect()->back()
            ->with('success', "Fee structure assigned to $assignedCount students successfully!");
    }

    /**
     * Generate installment schedule for a student
     */
    public function generateInstallmentSchedule($studentId, $feeStructureId)
    {
        $student = Student::findOrFail($studentId);
        $feeStructure = FeeStructure::with('feeStructureItems')->findOrFail($feeStructureId);

        $schedule = $this->generateInstallmentScheduleData($student, $feeStructure);

        return view('admin.installment-fees.schedule', compact('student', 'feeStructure', 'schedule'));
    }

    /**
     * Generate installment schedule data
     */
    private function generateInstallmentScheduleData($student, $feeStructure)
    {
        $schedule = [];
        $totalAmount = $feeStructure->feeStructureItems->sum('amount');
        $installmentAmount = $totalAmount / $feeStructure->installment_count;

        $startDate = Carbon::now()->startOfMonth();
        
        for ($i = 0; $i < $feeStructure->installment_count; $i++) {
            $dueDate = clone $startDate;
            
            // Adjust due date based on frequency
            switch ($feeStructure->installment_frequency) {
                case 'monthly':
                    $dueDate->addMonths($i);
                    break;
                case 'bimonthly':
                    $dueDate->addMonths($i * 2);
                    break;
                case 'quarterly':
                    $dueDate->addMonths($i * 3);
                    break;
            }

            // Set due day
            $dueDate->day = min($dueDate->daysInMonth, 15); // Default to 15th, max is last day of month

            $schedule[] = [
                'installment_number' => $i + 1,
                'due_date' => $dueDate,
                'amount' => round($installmentAmount, 2),
                'status' => $this->getInstallmentStatus($student->id, $feeStructure->id, $dueDate),
                'paid_date' => $this->getInstallmentPaymentDate($student->id, $feeStructure->id, $dueDate)
            ];
        }

        return $schedule;
    }

    /**
     * Get installment status
     */
    private function getInstallmentStatus($studentId, $feeStructureId, $dueDate)
    {
        // Check if payment exists for this installment period
        $payments = FeeCollection::where('student_id', $studentId)
            ->where('fee_structure_id', $feeStructureId)
            ->whereDate('payment_date', '<=', $dueDate)
            ->get();

        $totalExpected = 0;
        $totalPaid = 0;

        // Calculate expected amount based on due date
        $feeStructure = FeeStructure::with('feeStructureItems')->find($feeStructureId);
        $totalAmount = $feeStructure->feeStructureItems->sum('amount');
        $installmentAmount = $totalAmount / $feeStructure->installment_count;

        foreach ($payments as $payment) {
            $totalPaid += $payment->final_amount;
        }

        if ($totalPaid >= $installmentAmount) {
            return 'paid';
        } elseif ($totalPaid > 0) {
            return 'partial';
        } elseif ($dueDate->isPast()) {
            return 'overdue';
        } else {
            return 'pending';
        }
    }

    /**
     * Get installment payment date
     */
    private function getInstallmentPaymentDate($studentId, $feeStructureId, $dueDate)
    {
        $payment = FeeCollection::where('student_id', $studentId)
            ->where('fee_structure_id', $feeStructureId)
            ->whereDate('payment_date', '<=', $dueDate)
            ->orderBy('payment_date', 'desc')
            ->first();

        return $payment ? $payment->payment_date : null;
    }

    /**
     * Process installment payment
     */
    public function processInstallmentPayment(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'fee_structure_id' => 'required|exists:fee_structures,id',
            'installment_number' => 'required|integer',
            'amount_paid' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'payment_mode' => 'required|string',
            'remarks' => 'nullable|string',
            'submission_token' => 'nullable|string'
        ]);

        $student = Student::findOrFail($request->student_id);
        $feeStructure = FeeStructure::findOrFail($request->fee_structure_id);

        // Calculate installment amount
        $totalAmount = $feeStructure->feeStructureItems->sum('amount');
        $installmentAmount = $totalAmount / $feeStructure->installment_count;
        $lateFine = $this->calculateLateFine($request->student_id, $request->fee_structure_id, $request->payment_date);
        $userId = auth('web')->id();

        if (!$userId) {
            abort(403, 'Authenticated admin user required.');
        }

        // Generate fingerprint
        $fingerprint = $this->lockService->generateFingerprint($request->all(), $userId);
        
        $hasToken = $request->has('submission_token') && !empty($request->input('submission_token'));
        $token = $request->input('submission_token');

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
                DB::transaction(function () use ($request, $feeStructure, $installmentAmount, $lateFine, $userId) {
                    // Create fee collection record
                    $feeCollection = FeeCollection::create([
                        'receipt_no' => FeeCollection::generateReceiptNumber(),
                        'student_id' => $request->student_id,
                        'fee_structure_id' => $request->fee_structure_id,
                        'total_amount' => $installmentAmount,
                        'discount' => 0, // May need to add discount functionality
                        'late_fine' => $lateFine,
                        'final_amount' => $request->amount_paid + $lateFine,
                        'payment_date' => $request->payment_date,
                        'payment_mode' => $request->payment_mode,
                        'remarks' => $request->remarks,
                        'collected_by' => $userId
                    ]);

                    // Add fee collection items
                    foreach ($feeStructure->feeStructureItems as $item) {
                        $itemAmount = $item->amount / $feeStructure->installment_count;
                        FeeCollectionItem::create([
                            'fee_collection_id' => $feeCollection->id,
                            'fee_type_id' => $item->fee_type_id,
                            'amount' => $itemAmount,
                        ]);
                    }
                });

                return redirect()->back()
                    ->with('success', 'Installment payment recorded successfully!');

            } catch (\Illuminate\Database\QueryException $e) {
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

                return redirect()->back()->with('error', 'Failed to record installment payment due to database conflict. Please try again.');
            } catch (\Exception $e) {
                if ($hasToken) {
                    $this->lockService->releaseTokenLock($token);
                }
                $this->lockService->releaseFingerprintLock($fingerprint);

                return redirect()->back()->with('error', 'Failed to record installment payment: ' . $e->getMessage());
            }
        }
    }

    /**
     * Calculate late fine based on due date
     */
    private function calculateLateFine($studentId, $feeStructureId, $paymentDate)
    {
        return 0;
    }

    /**
     * View installment payment history
     */
    public function paymentHistory($studentId)
    {
        $student = Student::findOrFail($studentId);
        
        $paymentHistory = FeeCollection::where('student_id', $studentId)
            ->with(['feeStructure', 'collectedBy'])
            ->orderByDesc('payment_date')
            ->get();

        return view('admin.installment-fees.payment-history', compact('student', 'paymentHistory'));
    }

    /**
     * Generate installment report
     */
    public function generateReport(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'academic_year' => 'required|string'
        ]);

        $classId = $request->class_id;
        $academicYear = $request->academic_year;

        $students = Student::where(function ($q) use ($classId) {
                $q->where('class_id', $classId)->orWhere('school_class_id', $classId);
            })
            ->with(['feeAssignments.feeStructure.feeStructureItems'])
            ->get();

        $reportData = [];
        foreach ($students as $student) {
            $totalAmount = 0;
            $paidAmount = 0;
            $pendingAmount = 0;

            foreach ($student->feeAssignments as $assignment) {
                if ($assignment->feeStructure && $assignment->feeStructure->academic_year == $academicYear) {
                    $structureTotal = $assignment->feeStructure->feeStructureItems->sum('amount');
                    $totalAmount += $structureTotal;

                    // Calculate paid amount
                    $paid = FeeCollection::where('student_id', $student->id)
                        ->where('fee_structure_id', $assignment->fee_structure_id)
                        ->sum('final_amount');
                    
                    $paidAmount += $paid;
                    $pendingAmount += ($structureTotal - $paid);
                }
            }

            $reportData[] = [
                'student' => $student,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'pending_amount' => $pendingAmount,
                'payment_percentage' => $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 2) : 0
            ];
        }

        return view('admin.installment-fees.report', compact('reportData', 'academicYear'));
    }

    /**
     * Auto-generate late fines for overdue installments
     */
    public function autoGenerateLateFines()
    {
        // This would be run as a scheduled job
        $students = Student::with(['feeAssignments.feeStructure'])->get();

        $fineCount = 0;
        foreach ($students as $student) {
            foreach ($student->feeAssignments as $assignment) {
                if ($assignment->feeStructure) {
                    $this->processLateFinesForStudent($student, $assignment->feeStructure);
                    $fineCount++;
                }
            }
        }

        return redirect()->back()
            ->with('success', "Processed late fines for $fineCount student fee assignments.");
    }

    /**
     * Process late fines for a student and fee structure
     */
    private function processLateFinesForStudent($student, $feeStructure)
    {
        // Get installment schedule and check for overdue payments
        $schedule = $this->generateInstallmentScheduleData($student, $feeStructure);

        foreach ($schedule as $installment) {
            if ($installment['status'] === 'overdue' && !$this->hasLateFineApplied($student->id, $feeStructure->id, $installment['due_date'])) {
                // Apply late fine (in a real system, you'd add the fine to a late_fine table)
                // For now, we'll just log this as needing attention
            }
        }
    }

    /**
     * Check if late fine already applied
     */
    private function hasLateFineApplied($studentId, $feeStructureId, $dueDate)
    {
        // Check if late fine already applied for this installment
        return false; // Simplified for now
    }
}
