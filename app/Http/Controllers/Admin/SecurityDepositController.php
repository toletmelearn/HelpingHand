<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeeCollection;
use App\Models\FeeRefund;
use App\Models\SecurityDeposit;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SecurityDepositController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:accountant']);
    }

    /**
     * List security deposits, filterable by status. The queue accountants
     * work from -- primarily 'refund_pending' rows awaiting resolution.
     */
    public function index(Request $request)
    {
        $query = SecurityDeposit::with(['student.schoolClass', 'feeType']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->orderByRaw("status = 'refund_pending' desc");
        }

        $deposits = $query->latest()->paginate(20)->withQueryString();

        return view('admin.security-deposits.index', compact('deposits'));
    }

    /**
     * Resolve a refund_pending deposit. Never auto-triggered -- this is the
     * one manual approval step every held deposit must pass through before
     * money moves, per "never fully auto-return."
     *
     * action=refund: only allowed once the student's outstanding dues are
     * cleared (the "clearance" check) -- pays out the refund_amount that
     * was calculated at withdrawal time (deposit minus dues owed then) as
     * cash/bank, logged as a FeeRefund for consolidated reporting. Never
     * touches StudentFeeLedger -- this is money leaving the school's
     * books, not a tuition credit.
     *
     * action=adjust: for students who still owe money -- posts the portion
     * of the deposit that was already calculated as covering those dues
     * (amount - refund_amount) as a ledger credit, formally closing out
     * that debt. This is the one case a deposit touches the tuition ledger.
     */
    public function resolve(Request $request, $id)
    {
        $validated = $request->validate([
            'action' => 'required|in:refund,adjust',
            'payment_mode' => 'required_if:action,refund|nullable|string|in:' . implode(',', FeeCollection::PAYMENT_MODES),
            'refund_ref' => 'required_if:action,refund|nullable|string|max:100',
            'remarks' => 'nullable|string|max:500',
        ]);

        $deposit = SecurityDeposit::findOrFail($id);

        if ($deposit->status !== 'refund_pending') {
            return redirect()->back()->with('error', 'This deposit is not pending refund resolution.');
        }

        if ($validated['action'] === 'refund') {
            $outstanding = LedgerService::getOutstandingBalance($deposit->student_id);
            if ($outstanding > 0) {
                return redirect()->back()->with('error', sprintf(
                    'This student still has %.2f in outstanding dues -- clear those (or adjust the deposit against them) before refunding.',
                    $outstanding
                ));
            }

            DB::transaction(function () use ($deposit, $validated) {
                $deposit->update([
                    'status' => 'refunded',
                    'refund_mode' => $validated['payment_mode'],
                    'refund_ref' => $validated['refund_ref'],
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                    'remarks' => $validated['remarks'] ?? $deposit->remarks,
                ]);

                FeeRefund::create([
                    'student_id' => $deposit->student_id,
                    'fee_collection_id' => $deposit->fee_collection_id,
                    'amount' => $deposit->refund_amount,
                    'type' => 'deposit_refund',
                    'reason' => $validated['remarks'] ?? 'Security deposit refund',
                    'payment_mode' => $validated['payment_mode'],
                    'processed_by' => Auth::id(),
                    'processed_at' => now(),
                ]);
            });

            activity()->causedBy(Auth::user())
                ->performedOn($deposit)
                ->withProperties(['amount' => $deposit->refund_amount])
                ->log('security_deposit_refunded');

            return redirect()->back()->with('success', 'Deposit refund recorded.');
        }

        // action === 'adjust'
        $adjustedAmount = round((float) $deposit->amount - (float) $deposit->refund_amount, 2);

        DB::transaction(function () use ($deposit, $validated, $adjustedAmount) {
            $deposit->update([
                'status' => 'adjusted',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'remarks' => $validated['remarks'] ?? $deposit->remarks,
            ]);

            if ($adjustedAmount > 0) {
                LedgerService::postCredit(
                    $deposit->student_id,
                    now()->format('Y-m-d'),
                    "Security Deposit Adjusted Against Dues (Deposit #{$deposit->id})",
                    'security_deposit',
                    $deposit->id,
                    $adjustedAmount
                );
            }
        });

        activity()->causedBy(Auth::user())
            ->performedOn($deposit)
            ->withProperties(['amount' => $adjustedAmount])
            ->log('security_deposit_adjusted');

        return redirect()->back()->with('success', 'Deposit adjusted against outstanding dues.');
    }
}
