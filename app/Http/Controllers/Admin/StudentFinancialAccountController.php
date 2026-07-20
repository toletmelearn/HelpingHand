<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\FeeType;
use App\Services\FinanceAccountService;
use App\Models\StudentFinancialAccount;
use App\Models\StudentFeeLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class StudentFinancialAccountController extends Controller
{
    private \App\Services\LedgerService $ledger;

    public function __construct(\App\Services\LedgerService $ledger)
    {
        $this->middleware('auth');
        $this->ledger = $ledger;
    }

    /**
     * Display a listing of student accounts.
     */
    public function index(Request $request)
    {
        $query = Student::query()->with(['schoolClass', 'financialAccount']);

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('admission_no', 'LIKE', "%{$search}%");
            });
        }

        $students = $query->paginate(15);
        $classList = SchoolClass::orderBy('name')->get();

        return view('admin.financial-accounts.index', compact('students', 'classList'));
    }

    /**
     * Show the detailed financial statement screen for a student.
     */
    public function show($id, Request $request)
    {
        $student = Student::withTrashed()->with(['schoolClass', 'financialAccount'])->findOrFail($id);
        
        // Ensure student has a financial account
        $account = $student->financialAccount;
        if (!$account) {
            $account = StudentFinancialAccount::create([
                'student_id' => $student->id,
                'account_no' => 'FIN-' . str_pad($student->id, 6, '0', STR_PAD_LEFT),
                'status' => 'active',
                'created_by' => auth()->id(),
                'ip_address' => $request->ip()
            ]);
            $student->setRelation('financialAccount', $account);
        }

        $filters = $request->only(['academic_session', 'start_date', 'end_date', 'voucher_type', 'fee_head']);
        
        $cards = FinanceAccountService::getSummaryCards($student->id);
        $ledgerQuery = FinanceAccountService::getLedgerTimeline($student->id, $filters);
        
        $ledger = $ledgerQuery->paginate(20);
        
        // Build running balance array
        $allEntries = FinanceAccountService::getLedgerTimeline($student->id, $filters)->get();
        $runningBalance = 0.00;
        $runningBalances = [];
        foreach ($allEntries as $entry) {
            $runningBalance += $entry->debit - $entry->credit;
            $runningBalances[$entry->id] = $runningBalance;
        }

        $sessions = StudentFeeLedger::where('student_id', $student->id)
            ->whereNotNull('academic_year')
            ->distinct()
            ->pluck('academic_year')
            ->toArray();
            
        $voucherTypes = StudentFeeLedger::where('student_id', $student->id)
            ->whereNotNull('reference_type')
            ->distinct()
            ->pluck('reference_type')
            ->toArray();
            
        $feeHeads = FeeType::orderBy('name')->get();

        return view('admin.financial-accounts.show', compact(
            'student',
            'account',
            'cards',
            'ledger',
            'runningBalances',
            'sessions',
            'voucherTypes',
            'feeHeads',
            'filters'
        ));
    }

    /**
     * Post a manual adjustment entry (credit or debit).
     */
    public function postAdjustment(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:debit,credit',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
        ]);

        $student = Student::findOrFail($id);
        $amount = (float) $request->amount;
        $type = $request->type;
        $description = $request->description;

        DB::beginTransaction();
        try {
            $ledgerEntry = FinanceAccountService::postAdjustment($student->id, $type, $amount, $description, auth()->id());
            
            if ($ledgerEntry) {
                // Log activity to audit log
                if (class_exists(\App\Services\AuditLogService::class)) {
                    $audit = new \App\Services\AuditLogService();
                    $audit->log($ledgerEntry, 'create', [
                        'debit' => ['old' => null, 'new' => $ledgerEntry->debit],
                        'credit' => ['old' => null, 'new' => $ledgerEntry->credit],
                        'description' => ['old' => null, 'new' => $ledgerEntry->description]
                    ], auth()->id());
                }
                
                DB::commit();
                return redirect()->back()->with('success', 'Adjustment posted successfully.');
            }
            
            throw new \Exception('Failed to create ledger entry.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error posting adjustment: ' . $e->getMessage());
        }
    }

    /**
     * Export the statement history as PDF.
     */
    public function exportPdf($id, Request $request)
    {
        $student = Student::withTrashed()->with(['schoolClass', 'financialAccount'])->findOrFail($id);
        $filters = $request->only(['academic_session', 'start_date', 'end_date', 'voucher_type', 'fee_head']);
        
        $cards = FinanceAccountService::getSummaryCards($student->id);
        $ledger = FinanceAccountService::getLedgerTimeline($student->id, $filters)->get();
        
        $runningBalance = 0.00;
        $runningBalances = [];
        foreach ($ledger as $entry) {
            $runningBalance += $entry->debit - $entry->credit;
            $runningBalances[$entry->id] = $runningBalance;
        }

        $pdf = Pdf::loadView('admin.financial-accounts.statement-pdf', compact('student', 'cards', 'ledger', 'runningBalances'));
        return $pdf->download("financial_statement_{$student->admission_no}.pdf");
    }

    /**
     * Export the statement history as Excel (CSV).
     */
    public function exportExcel($id, Request $request)
    {
        $student = Student::withTrashed()->findOrFail($id);
        $filters = $request->only(['academic_session', 'start_date', 'end_date', 'voucher_type', 'fee_head']);
        $ledger = FinanceAccountService::getLedgerTimeline($student->id, $filters)->get();

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=financial_statement_{$student->admission_no}.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use($ledger, $student) {
            $file = fopen('php://output', 'w');
            
            // UTF-8 BOM for Excel formatting compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, ['STUDENT FINANCIAL STATEMENT']);
            fputcsv($file, ['Admission No:', $student->admission_no]);
            fputcsv($file, ['Name:', $student->name]);
            fputcsv($file, ['Class:', $student->schoolClass->name ?? $student->class]);
            fputcsv($file, ['Generated At:', now()->toDateTimeString()]);
            fputcsv($file, []);
            
            fputcsv($file, ['Date', 'Voucher Type', 'Reference No', 'Description', 'Debit', 'Credit', 'Running Balance']);

            $balance = 0.00;
            foreach ($ledger as $row) {
                $balance += $row->debit - $row->credit;
                fputcsv($file, [
                    $row->date ? $row->date->format('Y-m-d') : '',
                    ucwords(str_replace('_', ' ', $row->reference_type)),
                    $row->reference_id,
                    $row->description,
                    $row->debit > 0 ? number_format($row->debit, 2) : '0.00',
                    $row->credit > 0 ? number_format($row->credit, 2) : '0.00',
                    number_format($balance, 2)
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
