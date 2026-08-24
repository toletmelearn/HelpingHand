<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\AdminConfiguration;
use App\Models\PaymentClaim;
use App\Models\Student;
use App\Services\LedgerService;
use App\Services\UpiQrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ParentPaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('parent.auth');
    }

    public function showPaymentForm()
    {
        $parent = Auth::guard('parent')->user();
        $student = $parent->student;

        if (!$student) {
            abort(404, 'Linked student profile not found.');
        }

        // Fetch student's fee assignments to link structure ID
        $assignments = DB::table('student_fee_assignments')
            ->where('student_id', $student->id)
            ->get();

        $balance = LedgerService::getOutstandingBalance($student->id);
        $pendingFees = [];

        if ($balance > 0) {
            $firstAssign = $assignments->first();
            $feeStructureId = $firstAssign ? $firstAssign->fee_structure_id : 1;
            
            $pendingFees[] = [
                'assignment_id' => $firstAssign ? $firstAssign->id : 1,
                'fee_structure_id' => $feeStructureId,
                'name' => 'Outstanding Fees (Tuition, etc.)',
                'total_amount' => $balance,
                'paid_amount' => 0.00,
                'balance' => $balance,
            ];
        }


        $pendingClaims = \Illuminate\Support\Facades\Schema::hasTable('payment_claims')
            ? PaymentClaim::where('student_id', $student->id)->where('status', 'claimed')->latest()->get()
            : collect();

        $minimumPaymentAmount = $this->minimumPaymentAmount();

        return view('parent.payments.pay-fees', compact('pendingFees', 'student', 'pendingClaims', 'minimumPaymentAmount'));
    }

    /**
     * Per-student dynamic UPI QR -- am= defaults to the student's live
     * outstanding balance, but the parent can request a smaller partial
     * amount (validated against the balance and, if configured, a
     * per-school minimum). tr= is a fresh reference token that becomes the
     * payment_claims.reference_token once the parent submits their UTR.
     * No settlement happens here; this only generates a link to scan.
     */
    public function generateUpiQr(Request $request)
    {
        $parent = Auth::guard('parent')->user();
        $student = $parent->student;

        if (!$student) {
            abort(404, 'Linked student profile not found.');
        }

        $balance = LedgerService::getOutstandingBalance($student->id);
        if ($balance <= 0) {
            return response()->json(['status' => false, 'message' => 'No outstanding balance to pay.'], 422);
        }

        $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
        ]);

        $amount = $request->filled('amount') ? (float) $request->input('amount') : $balance;

        if ($amount > $balance) {
            return response()->json(['status' => false, 'message' => 'Amount cannot exceed the outstanding balance.'], 422);
        }

        $minimum = $this->minimumPaymentAmount();
        if ($minimum !== null && $amount < $minimum && $amount < $balance) {
            // The minimum only constrains a *partial* payment -- clearing
            // the full remaining balance (even if it's below the minimum)
            // must always be allowed.
            return response()->json(['status' => false, 'message' => "Minimum payment amount is ₹" . number_format($minimum, 2) . "."], 422);
        }

        $vpa = AdminConfiguration::get('fee', 'upi_vpa', '');
        if (!$vpa) {
            return response()->json(['status' => false, 'message' => 'Online UPI payment is not configured yet. Please contact the school office.'], 422);
        }

        $schoolName = AdminConfiguration::get('general', 'school_name', 'Helping Hand School');
        $referenceToken = 'PC-' . $student->id . '-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));

        $className = $student->schoolClass->name ?? $student->class ?? '';
        $period = now()->format('My');
        $transactionNote = sprintf('%s-%s-%s-%s', $student->admission_no ?? $student->id, $student->name, $className, $period);

        $result = UpiQrService::generate($vpa, $schoolName, $amount, $transactionNote, $referenceToken);

        return response()->json([
            'status' => true,
            'qr_code' => $result['qr_code'],
            'upi_uri' => $result['upi_uri'],
            'reference_token' => $referenceToken,
            'amount' => $amount,
        ]);
    }

    /**
     * Null means "no minimum configured" -- distinct from a configured
     * value of 0, and from an empty-string default when the setting has
     * never been saved.
     */
    private function minimumPaymentAmount(): ?float
    {
        $value = AdminConfiguration::get('fee', 'minimum_payment_amount', null);

        return ($value !== null && $value !== '') ? (float) $value : null;
    }

    /**
     * Parent submits the UTR after paying via UPI -- creates a claim,
     * status 'claimed'. No receipt, no ledger credit, until the matching
     * engine (or an accountant) confirms it against a bank statement row.
     */
    public function submitClaim(Request $request)
    {
        $parent = Auth::guard('parent')->user();
        $student = $parent->student;

        if (!$student) {
            abort(404, 'Linked student profile not found.');
        }

        $validated = $request->validate([
            'reference_token' => 'required|string|unique:payment_claims,reference_token',
            'utr' => 'required|string|size:12|regex:/^[0-9]+$/|unique:payment_claims,utr',
            'amount' => 'required|numeric|min:0.01',
            'screenshot' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
        ]);

        $minimum = $this->minimumPaymentAmount();
        $balance = LedgerService::getOutstandingBalance($student->id);
        if ($minimum !== null && $validated['amount'] < $minimum && $validated['amount'] < $balance) {
            return redirect()->back()
                ->withErrors(['amount' => "Minimum payment amount is ₹" . number_format($minimum, 2) . "."])
                ->withInput();
        }

        $path = null;
        if ($request->hasFile('screenshot')) {
            $path = $request->file('screenshot')->store('payment-claim-slips', 'public');
        }

        PaymentClaim::create([
            'student_id' => $student->id,
            'claim_type' => 'upi',
            'reference_token' => $validated['reference_token'],
            'utr' => $validated['utr'],
            'amount' => $validated['amount'],
            'screenshot_path' => $path,
            'status' => 'claimed',
            'submitted_at' => now(),
        ]);

        return redirect()->route('parent.payments.pay-fees')
            ->with('success', 'Your UTR has been submitted. We will verify and update your dues shortly.');
    }

    /**
     * Third payment path alongside counter cash and UPI: a bank cash
     * deposit slip, no UTR. Matched by the cash-deposit tier
     * (PaymentClaimMatchingService::tryCashDepositMatch()) on amount +
     * branch + date -- always suggested, never auto-confirmed, since
     * there's no UTR to key an exact match on. The slip photo is
     * *required* here (unlike UPI's optional screenshot) since it's the
     * only evidence the accountant has to approve against.
     */
    public function submitCashDepositClaim(Request $request)
    {
        $parent = Auth::guard('parent')->user();
        $student = $parent->student;

        if (!$student) {
            abort(404, 'Linked student profile not found.');
        }

        $validated = $request->validate([
            'deposit_date' => 'required|date|before_or_equal:today',
            'branch' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0.01',
            'slip' => 'required|file|mimes:jpeg,png,jpg,pdf|max:2048',
        ]);

        $path = $request->file('slip')->store('payment-claim-slips', 'public');
        $referenceToken = 'PC-' . $student->id . '-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));

        PaymentClaim::create([
            'student_id' => $student->id,
            'claim_type' => 'bank_cash_deposit',
            'reference_token' => $referenceToken,
            'utr' => null,
            'deposit_date' => $validated['deposit_date'],
            'branch' => $validated['branch'],
            'amount' => $validated['amount'],
            'screenshot_path' => $path,
            'status' => 'claimed',
            'submitted_at' => now(),
        ]);

        return redirect()->route('parent.payments.pay-fees')
            ->with('success', 'Your bank deposit slip has been submitted. We will verify and update your dues shortly.');
    }

    /**
     * There is no working payment gateway wired up yet -- the previous
     * "Pay with Card" button called a pure mock (PaymentGatewayService,
     * now removed) that logged a fake session and redirected back to this
     * same page without ever recording a payment. Rather than leave a
     * button that silently does nothing, this tells the parent honestly
     * that online card payment isn't available yet.
     */
    public function processStripePayment(Request $request)
    {
        $request->validate([
            'fee_structure_id' => 'required|integer',
            'amount' => 'required|numeric|min:1',
        ]);

        return redirect()->route('parent.payments.pay-fees')
            ->with('error', 'Online card payment is not available yet. Please contact the school office for payment options.');
    }

    // callbackSuccess() was removed here (P0 security fix): it accepted
    // student_id/fee_structure_id/amount as plain request input with no
    // ownership check and no real payment-gateway verification of any
    // kind, and credited a real fee_collections row via
    // LedgerService::allocateOnlinePayment() -- any authenticated parent
    // could fabricate a payment on any student's ledger via a crafted
    // GET request. It had no legitimate caller (no view/button ever
    // linked to it -- confirmed by repository-wide search) and no
    // functioning gateway ever called it either, since
    // processStripePayment() above was already disabled. The verified,
    // safe path for recording a real payment remains the existing
    // UPI-claim-matching workflow (submitClaim()/submitCashDepositClaim()
    // below, confirmed only by an admin/accountant or the matching engine
    // via PaymentClaimMatchingService -- never directly from parent
    // input). A future real payment gateway must post to a new,
    // ownership-and-signature-verified endpoint, not a resurrected
    // version of this one.
}
