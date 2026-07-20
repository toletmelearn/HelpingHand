<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentLeave;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentLeaveController extends Controller
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

        $leaves = StudentLeave::where('student_id', $student->id)->orderBy('start_date', 'desc')->get();

        return view('student.leaves.index', compact('leaves', 'student'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
        ]);

        $student = $this->getStudent();

        StudentLeave::create([
            'student_id' => $student->id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return redirect()->route('student.leaves.index')->with('success', 'Leave application submitted successfully.');
    }
}
