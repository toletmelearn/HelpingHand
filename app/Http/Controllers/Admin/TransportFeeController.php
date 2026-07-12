<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TransportFee;
use App\Models\Student;
use App\Models\Route;
use App\Models\Vehicle;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class TransportFeeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = TransportFee::with(['student', 'route', 'vehicle', 'collector']);

        if ($request->has('student_id') && !empty($request->student_id)) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $fees = $query->orderBy('academic_year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate(15);

        $students = Student::all();

        return view('admin.transport-fees.index', compact('fees', 'students'));
    }

    public function collectForm($id)
    {
        $fee = TransportFee::with(['student', 'route', 'vehicle'])->findOrFail($id);
        $paymentModes = ['cash' => 'Cash', 'upi' => 'UPI', 'bank' => 'Bank Transfer', 'card' => 'Card', 'online' => 'Online'];

        return view('admin.transport-fees.collect', compact('fee', 'paymentModes'));
    }

    public function collect(Request $request, $id)
    {
        $request->validate([
            'payment_mode' => 'required|in:' . implode(',', \App\Models\FeeCollection::PAYMENT_MODES),
            'payment_date' => 'required|date',
            'transaction_id' => [
                \Illuminate\Validation\Rule::requiredIf(function () use ($request) {
                    return in_array($request->payment_mode, ['bank', 'online']);
                }),
                'nullable',
                'string',
                'max:100'
            ],
            'remarks' => 'nullable|string|max:500'
        ]);

        $fee = TransportFee::findOrFail($id);

        if ($fee->status === 'paid') {
            return redirect()->back()->with('error', 'This transport fee is already paid.');
        }

        // Sequential Check: Prior Month Balances must be cleared first
        if ($this->hasPriorTransportDues($fee->student_id, $fee->month, $fee->academic_year)) {
            return redirect()->back()->with('error', 'Cannot process payment. Prior month transport balances must be cleared first.');
        }

        $receiptNo = 'TR-RCPT-' . strtoupper(uniqid());

        DB::transaction(function () use ($fee, $request, $receiptNo) {
            $fee->update([
                'status' => 'paid',
                'payment_date' => $request->payment_date,
                'payment_mode' => $request->payment_mode,
                'transaction_id' => $request->transaction_id,
                'receipt_no' => $receiptNo,
                'collected_by' => auth()->id(),
                'remarks' => $request->remarks
            ]);

            // Post Credit to Ledger
            LedgerService::postCredit(
                $fee->student_id,
                $request->payment_date,
                "Transport Fee Payment Received - {$fee->month} ({$fee->academic_year}) [Receipt: {$receiptNo}]",
                'student_transport_due', // reference_type (linked to StudentTransportDue/transport_fees)
                $fee->id,
                $fee->amount
            );
        });

        return redirect()->route('admin.transport-fees.index')->with('success', 'Transport fee collected successfully. Receipt No: ' . $receiptNo);
    }

    public function generateDues(Request $request)
    {
        $request->validate([
            'month' => 'required|string',
            'academic_year' => 'required|string'
        ]);

        Artisan::call('transport:generate-dues', [
            'month' => $request->month,
            'academic_year' => $request->academic_year
        ]);

        return redirect()->route('admin.transport-fees.index')->with('success', 'Transport dues generated successfully.');
    }

    private function hasPriorTransportDues($studentId, $targetMonth, $targetAcademicYear)
    {
        $monthsSequence = ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];
        
        $unpaidFees = TransportFee::where('student_id', $studentId)
            ->where('status', 'unpaid')
            ->get();

        foreach ($unpaidFees as $item) {
            if ($item->academic_year < $targetAcademicYear) {
                return true;
            }

            if ($item->academic_year === $targetAcademicYear) {
                $targetIndex = array_search($targetMonth, $monthsSequence);
                $itemIndex = array_search($item->month, $monthsSequence);

                if ($targetIndex !== false && $itemIndex !== false && $itemIndex < $targetIndex) {
                    return true;
                }
            }
        }

        return false;
    }
}
