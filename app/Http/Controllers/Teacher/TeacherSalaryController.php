<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TeacherSalary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class TeacherSalaryController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:teacher');
    }

    /**
     * List all salary payouts for the authenticated teacher.
     */
    public function index()
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin->teacher;

        if (!$teacher) {
            return redirect()->route('teacher.dashboard')->with('error', 'Teacher record not found.');
        }

        $salaries = TeacherSalary::where('teacher_id', $teacher->id)
            ->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('teacher.salaries.index', compact('salaries', 'teacher'));
    }

    /**
     * Download the salary slip PDF for the authenticated teacher.
     */
    public function downloadSlip($id)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin->teacher;

        if (!$teacher) {
            return redirect()->route('teacher.dashboard')->with('error', 'Teacher record not found.');
        }

        // Securely fetch only if the salary belongs to this teacher
        $salary = TeacherSalary::where('teacher_id', $teacher->id)
            ->with(['teacher', 'paidBy'])
            ->findOrFail($id);

        $pdf = PDF::loadView('admin.hr.payroll.slip-pdf', compact('salary'));
        return $pdf->download("salary_slip_{$salary->id}.pdf");
    }
}
