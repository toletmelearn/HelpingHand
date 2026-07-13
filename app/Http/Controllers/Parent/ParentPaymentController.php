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
                'name' => 'Outstanding Fees (Tuition, Transport, etc.)',
                'total_amount' => $balance,
                'paid_amount' => 0.00,
                'balance' => $balance,
            ];
        }


        $pendingClaims = \Illuminate\Support\Facades\Schema::hasTable('payment_claims')
            ? PaymentClaim::where('student_id', $student->id)->where('status', 'claimed')->latest()->get()
            : collect();

        return view('parent.payments.pay-fees', compact('pendingFees', 'student', 'pendingClaims'));
    }

    /**
     * Per-student dynamic UPI QR -- am= is the student's live outstanding
     * balance, tr= is a fresh reference token that becomes the
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

        $vpa = AdminConfiguration::get('fee', 'upi_vpa', '');
        if (!$vpa) {
            return response()->json(['status' => false, 'message' => 'Online UPI payment is not configured yet. Please contact the school office.'], 422);
        }

        $schoolName = AdminConfiguration::get('general', 'school_name', 'Helping Hand School');
        $referenceToken = 'PC-' . $student->id . '-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));

        $className = $student->schoolClass->name ?? $student->class ?? '';
        $period = now()->format('My');
        $transactionNote = sprintf('%s-%s-%s-%s', $student->admission_no ?? $student->id, $student->name, $className, $period);

        $result = UpiQrService::generate($vpa, $schoolName, $balance, $transactionNote, $referenceToken);

        return response()->json([
            'status' => true,
            'qr_code' => $result['qr_code'],
            'upi_uri' => $result['upi_uri'],
            'reference_token' => $referenceToken,
            'amount' => $balance,
        ]);
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

        $path = null;
        if ($request->hasFile('screenshot')) {
            $path = $request->file('screenshot')->store('payment-claim-slips', 'public');
        }

        PaymentClaim::create([
            'student_id' => $student->id,
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
     * There is no working payment gateway wired up yet -- the previous
     * "Pay with Card" button called a pure mock (PaymentGatewayService,
     * now removed) that logged a fake session and redirected back to this
     * same page without ever recording a payment or reaching
     * callbackSuccess() below. Rather than leave a button that silently
     * does nothing, this now tells the parent honestly that online card
     * payment isn't available yet. callbackSuccess() stays fully working
     * as the tested payment-recording endpoint -- it's the shared target
     * a real online payment method (e.g. the planned UPI flow) will post
     * to once one exists.
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

    public function callbackSuccess(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'fee_structure_id' => 'required',
            'amount' => 'required|numeric',
        ]);

        LedgerService::allocateOnlinePayment(
            (int) $request->student_id,
            (float) $request->amount,
            'online',
            [
                'fee_structure_id' => $request->fee_structure_id,
                'remarks' => 'Online payment via parent checkout portal',
            ]
        );

        return redirect()->route('parent.payments.pay-fees')->with('success', "Payment of ₹{$request->amount} processed successfully.");
    }
}
