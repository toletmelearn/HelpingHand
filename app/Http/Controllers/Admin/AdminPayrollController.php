<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeacherSalary;
use App\Models\Teacher;
use App\Services\Payroll\AttendanceDeductionCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class AdminPayrollController extends Controller
{
    private AttendanceDeductionCalculator $deductionCalculator;

    public function __construct(AttendanceDeductionCalculator $deductionCalculator)
    {
        $this->middleware('auth');
        $this->deductionCalculator = $deductionCalculator;
    }

    public function index(Request $request)
    {
        $query = TeacherSalary::with('teacher');

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        $salaries = $query->orderBy('created_at', 'desc')->paginate(15);
        $teachers = Teacher::all();

        // Retrieve configured default percentages
        $hraPercent = \App\Models\AdminConfiguration::get('payroll', 'payroll_hra_percent', '10.00');
        $daPercent = \App\Models\AdminConfiguration::get('payroll', 'payroll_da_percent', '5.00');
        $taPercent = \App\Models\AdminConfiguration::get('payroll', 'payroll_ta_percent', '2.00');
        $medicalPercent = \App\Models\AdminConfiguration::get('payroll', 'payroll_medical_percent', '1.50');
        $pfPercent = \App\Models\AdminConfiguration::get('payroll', 'payroll_pf_percent', '12.00');
        $esiPercent = \App\Models\AdminConfiguration::get('payroll', 'payroll_esi_percent', '0.75');

        return view('admin.hr.payroll.index', compact(
            'salaries', 
            'teachers',
            'hraPercent',
            'daPercent',
            'taPercent',
            'medicalPercent',
            'pfPercent',
            'esiPercent'
        ));
    }

    /**
     * Compute attendance/leave-based deduction days for a teacher + month, so
     * the "Generate Payroll" form can show it before the admin submits. This is
     * a preview only -- the admin can still edit the figure before saving.
     */
    public function previewDeduction(Request $request)
    {
        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'pay_month' => 'required|integer|min:1|max:12',
            'pay_year' => 'required|integer|min:2000|max:2100',
        ]);

        $teacher = Teacher::findOrFail($request->teacher_id);
        $breakdown = $this->deductionCalculator->calculate($teacher, (int) $request->pay_month, (int) $request->pay_year);

        return response()->json($breakdown);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'pay_month' => 'required|integer|min:1|max:12',
            'pay_year' => 'required|integer|min:2000|max:2100',
            'pay_scale' => 'nullable|string',
            'basic_salary' => 'required|numeric|min:0',
            'hra' => 'nullable|numeric|min:0',
            'da' => 'nullable|numeric|min:0',
            'ta' => 'nullable|numeric|min:0',
            'medical_allowance' => 'nullable|numeric|min:0',
            'special_allowance' => 'nullable|numeric|min:0',
            'pf_amount' => 'nullable|numeric|min:0',
            'esi_amount' => 'nullable|numeric|min:0',
            'tax_deduction' => 'nullable|numeric|min:0',
            'other_deductions' => 'nullable|numeric|min:0',
            'attendance_deduction_days' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string',
            'reference_number' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        $basic = $request->basic_salary;
        $hra = $request->hra ?? 0;
        $da = $request->da ?? 0;
        $ta = $request->ta ?? 0;
        $medical = $request->medical_allowance ?? 0;
        $special = $request->special_allowance ?? 0;

        $gross = $basic + $hra + $da + $ta + $medical + $special;

        $pf = $request->pf_amount ?? 0;
        $esi = $request->esi_amount ?? 0;
        $tax = $request->tax_deduction ?? 0;
        $other = $request->other_deductions ?? 0;

        // The admin can leave this blank to skip attendance-based deductions
        // entirely, or override the auto-computed figure before submitting.
        $attendanceDeductionDays = $request->filled('attendance_deduction_days')
            ? (float) $request->attendance_deduction_days
            : 0;
        $attendanceDeductionAmount = $attendanceDeductionDays > 0
            ? round($attendanceDeductionDays * $this->deductionCalculator->perDayRate($gross), 2)
            : 0;

        $deductions = $pf + $esi + $tax + $other + $attendanceDeductionAmount;
        $net = max(0, $gross - $deductions);

        $salary = TeacherSalary::create([
            'teacher_id' => $request->teacher_id,
            'pay_month' => $request->pay_month,
            'pay_year' => $request->pay_year,
            'pay_scale' => $request->pay_scale,
            'basic_salary' => $basic,
            'hra' => $hra,
            'da' => $da,
            'ta' => $ta,
            'medical_allowance' => $medical,
            'special_allowance' => $special,
            'gross_salary' => $gross,
            'pf_amount' => $pf,
            'esi_amount' => $esi,
            'tax_deduction' => $tax,
            'other_deductions' => $other,
            'attendance_deduction_days' => $attendanceDeductionDays ?: null,
            'attendance_deduction_amount' => $attendanceDeductionAmount ?: null,
            'net_salary' => $net,
            'payment_status' => 'paid',
            'payment_date' => now(),
            'payment_method' => $request->payment_method,
            'reference_number' => $request->reference_number,
            'paid_by' => Auth::id(),
            'remarks' => $request->remarks,
        ]);

        return redirect()->route('admin.hr.payroll.index')
            ->with('success', 'Payroll slip generated and marked as paid.');
    }

    public function downloadSlip($id)
    {
        $salary = TeacherSalary::with(['teacher', 'paidBy'])->findOrFail($id);
        $pdf = PDF::loadView('admin.hr.payroll.slip-pdf', compact('salary'));
        return $pdf->download("salary_slip_{$salary->id}.pdf");
    }
}
