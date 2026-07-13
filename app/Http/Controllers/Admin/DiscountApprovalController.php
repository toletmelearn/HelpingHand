<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DiscountApproval;
use App\Models\StudentDiscountApplied;
use App\Models\Student;
use App\Models\DiscountRule;
use App\Models\FeeType;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class DiscountApprovalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        // Restrict list access if needed, but Clerk and Accountant need to verify, and Admin needs to see.
        $query = DiscountApproval::with(['student', 'discountRule', 'feeType', 'creator', 'verifier']);

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $approvals = $query->orderBy('created_at', 'desc')->paginate(15);
        $students = Student::all();
        $rules = DiscountRule::all();
        $feeTypes = FeeType::all();

        return view('admin.discount-approvals.index', compact('approvals', 'students', 'rules', 'feeTypes'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        
        // Restrict discount creation permissions strictly to 'Admin' and 'Accountant'
        if (!$user->hasRole('admin') && $user->role !== 'admin' && !$user->hasRole('accountant') && $user->role !== 'accountant') {
            abort(403, 'Unauthorized. Only Admin and Accountant can create discounts.');
        }

        $request->validate([
            'student_id' => 'required|exists:students,id',
            'discount_rule_id' => 'required|exists:discount_rules,id',
            'fee_type_id' => 'required|exists:fee_types,id',
            'amount' => 'required|numeric|min:0.01',
            'month' => 'required|string',
            'academic_year' => 'required|string',
        ]);

        \App\Services\FinancialYearClosingService::checkIfSessionIsClosed($request->academic_year);

        $status = 'pending_verification';
        
        // If Accountant creates it, does it need verification?
        // "Admin-Initiated Discount Loop: If an Admin applies a discount via the Admin Dashboard, do not apply it immediately. Change its status to pending_verification."
        // We will default all created ones to pending_verification if created by Admin.
        if ($user->hasRole('admin') || $user->role === 'admin') {
            $status = 'pending_verification';
        } else {
            // For Accountant, let's also allow pending or active if they upload slip immediately. To keep it uniform and strictly guarded:
            $status = 'pending_verification';
        }

        DiscountApproval::create([
            'student_id' => $request->student_id,
            'discount_rule_id' => $request->discount_rule_id,
            'fee_type_id' => $request->fee_type_id,
            'amount' => $request->amount,
            'month' => $request->month,
            'academic_year' => $request->academic_year,
            'status' => $status,
            'created_by' => $user->id
        ]);

        return redirect()->route('admin.discount-approvals.index')
            ->with('success', 'Discount application submitted. It requires physical verification slip upload to become active.');
    }

    public function verify(Request $request, $id)
    {
        $user = auth()->user();

        // Accountant or Clerk must verify/upload
        if (!$user->hasRole('accountant') && $user->role !== 'accountant' && !$user->hasRole('clerk') && $user->role !== 'clerk') {
            abort(403, 'Unauthorized. Only Accountant and Clerk can verify discounts and upload slips.');
        }

        $request->validate([
            'verification_slip' => 'required|file|mimes:pdf,jpeg,png,jpg|max:2048'
        ]);

        $approval = DiscountApproval::findOrFail($id);

        \App\Services\FinancialYearClosingService::checkIfSessionIsClosed($approval->academic_year);

        if ($approval->status !== 'pending_verification') {
            return redirect()->back()->with('error', 'This discount is already verified or rejected.');
        }

        // Upload file
        $path = $request->file('verification_slip')->store('verification_slips', 'public');

        DB::transaction(function () use ($approval, $path, $user) {
            $approval->update([
                'status' => 'active',
                'verification_slip' => $path,
                'verified_by' => $user->id
            ]);

            // Create snapshot in student_discounts_applied table to make it active!
            StudentDiscountApplied::create([
                'student_id' => $approval->student_id,
                'discount_rule_id' => $approval->discount_rule_id,
                'fee_type_id' => $approval->fee_type_id,
                'amount' => $approval->amount,
                'month' => $approval->month,
                'academic_year' => $approval->academic_year,
                'applied_at' => now(),
                'approved_by' => $user->id,
            ]);
        });

        return redirect()->route('admin.discount-approvals.index')
            ->with('success', 'Discount verified successfully and is now active!');
    }

    public function reject(Request $request, $id)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin') && $user->role !== 'admin' && !$user->hasRole('accountant') && $user->role !== 'accountant') {
            abort(403, 'Unauthorized.');
        }

        $approval = DiscountApproval::findOrFail($id);
        $approval->update([
            'status' => 'rejected'
        ]);

        return redirect()->route('admin.discount-approvals.index')
            ->with('success', 'Discount request rejected.');
    }
}
