<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use App\Models\MedicalCheckup;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;

class StudentMedicalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function getStudent()
    {
        $user = Auth::user();
        return Student::where('user_id', $user->id)->first() ?? $user->student;
    }

    public function index()
    {
        $student = $this->getStudent();
        if (!$student) {
            return redirect()->back()->with('error', 'Student profile not found.');
        }

        $record = MedicalRecord::where('student_id', $student->id)->first();
        $checkups = MedicalCheckup::where('student_id', $student->id)->orderBy('checkup_date', 'desc')->get();

        return view('student.medical.index', compact('record', 'checkups', 'student'));
    }
}
