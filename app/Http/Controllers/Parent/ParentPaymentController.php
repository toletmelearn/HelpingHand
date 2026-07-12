<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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


        return view('parent.payments.pay-fees', compact('pendingFees', 'student'));
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
