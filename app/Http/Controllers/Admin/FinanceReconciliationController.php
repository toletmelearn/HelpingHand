<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentFeeLedger;
use App\Models\FeeRefund;
use App\Models\SchoolClass;
use App\Services\AuditLogService;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class FinanceReconciliationController extends Controller
{
    protected $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->middleware(['auth', 'role:accountant']);
        $this->auditLogService = $auditLogService;
    }

    /**
     * Display unresolved ledger records.
     */
    public function unresolved(Request $request)
    {
        $query = StudentFeeLedger::where(function ($q) {
            $q->whereNull('academic_year')
              ->orWhereNull('class_id');
        })->with(['student.schoolClass', 'feeType']);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhereHas('student', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('admission_no', 'like', "%{$search}%");
                  });
            });
        }

        // Reference type filter
        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }

        $records = $query->latest('date')->paginate(20)->withQueryString();
        $classes = SchoolClass::active()->get();
        $academicYears = DB::table('fee_structures')->distinct()->pluck('academic_year')->filter();

        return view('admin.reconciliation.unresolved', compact('records', 'classes', 'academicYears'));
    }

    /**
     * Display overpayments report.
     */
    public function overpayments(Request $request)
    {
        $query = \App\Models\Student::withTrashed()
            ->select('students.*')
            ->selectRaw('(SELECT SUM(debit) - SUM(credit) FROM student_fee_ledgers WHERE student_fee_ledgers.student_id = students.id) as outstanding')
            ->having('outstanding', '<', 0)
            ->with('schoolClass');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('admission_no', 'like', "%{$search}%");
        }

        $students = $query->paginate(20)->withQueryString();

        return view('admin.reconciliation.overpayments', compact('students'));
    }

    /**
     * Display refund and reversal audits.
     */
    public function refunds(Request $request)
    {
        $query = FeeRefund::with(['student.schoolClass', 'processedBy']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', function ($sq) use ($search) {
                $sq->where('name', 'like', "%{$search}%")
                  ->orWhere('admission_no', 'like', "%{$search}%");
            });
        }

        $refunds = $query->latest('processed_at')->paginate(20)->withQueryString();

        return view('admin.reconciliation.refunds', compact('refunds'));
    }

    /**
     * Display orphan ledger records.
     */
    public function orphans(Request $request)
    {
        $query = StudentFeeLedger::with('student.schoolClass')
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('reference_type', 'fee_structure_item')
                      ->whereNotExists(function ($e) {
                          $e->select(DB::raw(1))
                            ->from('fee_structure_items')
                            ->whereColumn('fee_structure_items.id', 'student_fee_ledgers.reference_id');
                      });
                })->orWhere(function ($sub) {
                    $sub->where('reference_type', 'fee_collection')
                      ->whereNotExists(function ($e) {
                          $e->select(DB::raw(1))
                            ->from('fee_collections')
                            ->whereColumn('fee_collections.id', 'student_fee_ledgers.reference_id');
                      });
                })->orWhere(function ($sub) {
                    $sub->where('reference_type', 'fee_refund')
                      ->whereNotExists(function ($e) {
                          $e->select(DB::raw(1))
                            ->from('fee_refunds')
                            ->whereColumn('fee_refunds.id', 'student_fee_ledgers.reference_id');
                      });
                });
            });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', function ($sq) use ($search) {
                $sq->where('name', 'like', "%{$search}%")
                  ->orWhere('admission_no', 'like', "%{$search}%");
            });
        }

        $records = $query->paginate(20)->withQueryString();

        return view('admin.reconciliation.orphans', compact('records'));
    }

    /**
     * Display ledger balance mismatches.
     */
    public function mismatches(Request $request)
    {
        $query = \App\Models\Student::withTrashed()
            ->select('students.*')
            ->selectRaw('(SELECT SUM(debit) - SUM(credit) FROM student_fee_ledgers WHERE student_fee_ledgers.student_id = students.id) as calculated_balance')
            ->selectRaw('(SELECT running_balance FROM student_fee_ledgers WHERE student_fee_ledgers.student_id = students.id ORDER BY date DESC, id DESC LIMIT 1) as latest_running_balance')
            ->havingRaw('calculated_balance != latest_running_balance OR (calculated_balance IS NOT NULL AND latest_running_balance IS NULL) OR (calculated_balance IS NULL AND latest_running_balance IS NOT NULL)')
            ->with('schoolClass');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('admission_no', 'like', "%{$search}%");
        }

        $students = $query->paginate(20)->withQueryString();

        return view('admin.reconciliation.mismatches', compact('students'));
    }

    /**
     * Bulk assign class and academic year to unresolved records.
     */
    public function bulkAssign(Request $request)
    {
        $request->validate([
            'ledger_ids' => 'required|array',
            'ledger_ids.*' => 'exists:student_fee_ledgers,id',
            'class_id' => 'nullable|exists:school_classes,id',
            'academic_year' => 'nullable|string',
        ]);

        $ledgerIds = $request->ledger_ids;
        $classId = $request->class_id;
        $academicYear = $request->academic_year;

        DB::transaction(function () use ($ledgerIds, $classId, $academicYear) {
            $ledgers = StudentFeeLedger::whereIn('id', $ledgerIds)->get();
            $studentIds = [];

            foreach ($ledgers as $ledger) {
                $oldClassId = $ledger->class_id;
                $oldAcademicYear = $ledger->academic_year;

                $updateData = [];
                if ($classId !== null) {
                    $updateData['class_id'] = $classId;
                }
                if ($academicYear !== null) {
                    $updateData['academic_year'] = $academicYear;
                }

                if (!empty($updateData)) {
                    $ledger->update($updateData);

                    // Log field changes individually
                    if (array_key_exists('class_id', $updateData)) {
                        $this->auditLogService->logFieldChange($ledger, 'class_id', $oldClassId, $classId);
                    }
                    if (array_key_exists('academic_year', $updateData)) {
                        $this->auditLogService->logFieldChange($ledger, 'academic_year', $oldAcademicYear, $academicYear);
                    }

                    $studentIds[$ledger->student_id] = true;
                }
            }

            // Rebuild outstanding running balances for affected students
            foreach (array_keys($studentIds) as $studentId) {
                LedgerService::rebuildRunningBalances($studentId);
            }
        });

        return redirect()->back()->with('success', 'Selected records resolved successfully.');
    }

    /**
     * Rebuild ledger running balances for a specific student.
     */
    public function rebuildLedger(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
        ]);

        LedgerService::rebuildRunningBalances($request->student_id);

        return redirect()->back()->with('success', 'Ledger running balances rebuilt successfully.');
    }

    /**
     * Issue a partial or full refund for a student's overpaid/excess
     * balance (e.g. caution money, excess fee). RefundService::issueRefund()
     * already existed, fully built and tested, but had no route/UI
     * anywhere -- the overpayments report above was read-only.
     */
    public function issueRefund(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_mode' => 'required|string|in:' . implode(',', \App\Models\FeeCollection::PAYMENT_MODES),
            'reason' => 'nullable|string|max:500',
        ]);

        $outstanding = LedgerService::getOutstandingBalance($validated['student_id']);
        if ($outstanding >= 0) {
            return redirect()->back()->with('error', 'This student has no overpaid balance to refund.');
        }
        if ($validated['amount'] > abs($outstanding)) {
            return redirect()->back()->with('error', sprintf(
                'Refund amount (%.2f) exceeds the overpaid balance (%.2f).',
                $validated['amount'],
                abs($outstanding)
            ));
        }

        \App\Services\RefundService::issueRefund(
            (int) $validated['student_id'],
            null,
            (float) $validated['amount'],
            $validated['payment_mode'],
            $validated['reason'] ?: 'Excess fee refund'
        );

        activity()->causedBy(Auth::user())
            ->withProperties(['student_id' => $validated['student_id'], 'amount' => $validated['amount']])
            ->log('fee_refund_issued');

        return redirect()->back()->with('success', 'Refund issued successfully.');
    }
}
