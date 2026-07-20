<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Models\Student;
use App\Models\FeeCollection;
use App\Models\FeeStructure;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class ParentDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('parent.auth');
    }

    public function index()
    {
        try {
            $parent = Auth::guard('parent')->user();
            
            if (!$parent) {
                return redirect()->route('parent.login');
            }
            
            // Get the parent's linked student
            $student = $parent->student;
            
            if (!$student) {
                return redirect()->route('parent.login')->withErrors([
                    'error' => 'Student not linked to parent'
                ]);
            }
        
            // Get fee structure for student's class
            $feeStructure = FeeStructure::where('class_name', $student->class)
                ->where('status', 'active')
                ->first();
        
            // Calculate total yearly fee from the ledger debits
            $totalYearlyFee = \App\Models\StudentFeeLedger::where('student_id', $student->id)
                ->where('debit', '>', 0)
                ->sum('debit');
        
            // Calculate total paid amount from the ledger credits
            $totalPaid = \App\Models\StudentFeeLedger::where('student_id', $student->id)
                ->where('credit', '>', 0)
                ->sum('credit');
        
            // Calculate pending amount using getOutstandingBalance
            $pendingAmount = \App\Services\LedgerService::getOutstandingBalance($student->id);
        
            // Get recent payment history
            $paymentHistory = $student->feeCollections()
                ->with('feeCollectionItems.feeType')
                ->latest()
                ->take(5)
                ->get();
        
            $students = $parent->students;
            if ($students->isEmpty()) {
                $students = collect($student ? [$student] : []);
            }

            if ($student && !session()->has('active_student_id')) {
                session(['active_student_id' => $student->id]);
            }
        
            return view('parent.dashboard', compact(
                'student', 
                'students',
                'feeStructure', 
                'totalYearlyFee', 
                'totalPaid', 
                'pendingAmount', 
                'paymentHistory'
            ));
        } catch (Exception $e) {
            return redirect()->route('parent.login')->withErrors([
                'error' => 'An error occurred. Please try again.'
            ]);
        }
    }
    
    public function paymentHistory()
    {
        $parent = Auth::guard('parent')->user();
        
        if (!$parent) {
            return redirect()->route('parent.login');
        }
        
        // Get the parent's linked student
        $student = $parent->student;
        
        if (!$student) {
            return redirect()->route('parent.login')->withErrors([
                'error' => 'Student not linked to parent'
            ]);
        }
        
        $paymentHistory = $student->feeCollections()
            ->with('feeCollectionItems.feeType')
            ->latest()
            ->get();
        
        return view('parent.payment-history', compact('paymentHistory', 'student'));
    }
    
    public function feeStructure()
    {
        $parent = Auth::guard('parent')->user();
        
        if (!$parent) {
            return redirect()->route('parent.login');
        }
        
        // Get the parent's linked student
        $student = $parent->student;
        
        if (!$student) {
            return redirect()->route('parent.login')->withErrors([
                'error' => 'Student not linked to parent'
            ]);
        }
        
        $feeStructure = FeeStructure::with('feeStructureItems.feeType')
            ->where('class_name', $student->class)
            ->where('status', 'active')
            ->first();

        $feeItems = $feeStructure ? $feeStructure->feeStructureItems : collect();

        $parentDisplayFrequency = \App\Models\AdminConfiguration::get('fees', 'parent_display_frequency', 'monthly');
        $displayRows = $this->buildFeeDisplayRows($feeItems, $parentDisplayFrequency);

        return view('parent.fee-structure', compact('student', 'feeStructure', 'feeItems', 'parentDisplayFrequency', 'displayRows'));
    }

    /**
     * Expands every fee-structure item into its real per-occurrence charges
     * for the parent fee structure page, so the displayed total always
     * equals the true annual amount regardless of the Monthly/Quarterly
     * display setting. Display-only -- never touches actual billing;
     * ledger generation, due dates, and late fees stay exactly as
     * configured on the fee structure itself.
     *
     * - 'quarterly' items (genuinely billed once per quarter, e.g. Tuition
     *   at a school like Pushp Niketan) are always shown per-quarter --
     *   there's no coarser "monthly" breakdown to fall back to, since the
     *   underlying billing has no monthly granularity to begin with.
     * - 'monthly' items are shown one row per month when the setting is
     *   Monthly, or grouped/summed into quarters when it's Quarterly.
     * - one-time / yearly / session-wise / exam-wise / custom items are
     *   always a single line -- they don't repeat, so there's nothing to
     *   break out by month or quarter.
     */
    private function buildFeeDisplayRows($feeItems, string $displayFrequency): array
    {
        $quarters = [
            'Q1 (Apr - Jun)' => ['Q1', 'April', 'May', 'June'],
            'Q2 (Jul - Sep)' => ['Q2', 'July', 'August', 'September'],
            'Q3 (Oct - Dec)' => ['Q3', 'October', 'November', 'December'],
            'Q4 (Jan - Mar)' => ['Q4', 'January', 'February', 'March'],
        ];

        $rows = [];

        foreach ($feeItems as $item) {
            $frequency = $item->billing_frequency ?? 'monthly';
            $feeTypeName = $item->feeType->name ?? 'N/A';
            $amount = (float) $item->amount;
            $chargeMonths = is_array($item->charge_months) ? $item->charge_months : [];

            if ($frequency === 'quarterly') {
                foreach ($quarters as $quarterLabel => $tokens) {
                    if (empty(array_intersect($chargeMonths, $tokens))) {
                        continue;
                    }
                    $rows[] = ['label' => "{$feeTypeName} -- {$quarterLabel}", 'amount' => $amount];
                }
                continue;
            }

            if ($frequency === 'monthly') {
                if ($displayFrequency === 'quarterly') {
                    foreach ($quarters as $quarterLabel => $tokens) {
                        $count = count(array_intersect($chargeMonths, $tokens));
                        if ($count === 0) {
                            continue;
                        }
                        $rows[] = ['label' => "{$feeTypeName} -- {$quarterLabel}", 'amount' => $amount * $count];
                    }
                } else {
                    foreach ($chargeMonths as $month) {
                        $rows[] = ['label' => "{$feeTypeName} -- {$month}", 'amount' => $amount];
                    }
                }
                continue;
            }

            // One-time / yearly / session-wise / exam-wise / custom -- single line.
            $rows[] = ['label' => $feeTypeName, 'amount' => $amount];
        }

        return $rows;
    }
    
    public function downloadReceipt($id)
    {
        $parent = Auth::guard('parent')->user();
        
        if (!$parent) {
            return redirect()->route('parent.login');
        }
        
        // Get the parent's linked student
        $student = $parent->student;
        
        if (!$student) {
            return redirect()->route('parent.login')->withErrors([
                'error' => 'Student not linked to parent'
            ]);
        }
        
        $feeCollection = FeeCollection::withTrashed()->with([
            'student.schoolClass',
            'feeStructure',
            'feeCollectionItems.feeType',
            'collectedBy'
        ])->findOrFail($id);
        
        // Verify this receipt belongs to the logged in parent
        if ($feeCollection->student_id !== $student->id) {
            abort(403, 'Unauthorized access to receipt');
        }
        
        $pdf = PDF::loadView('admin.fees.receipt-pdf', compact('feeCollection'));
        return $pdf->download('receipt-' . $feeCollection->receipt_no . '.pdf');
    }
}