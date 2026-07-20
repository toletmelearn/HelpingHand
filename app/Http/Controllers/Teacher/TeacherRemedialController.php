<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SlowLearner;
use Illuminate\Http\Request;

class TeacherRemedialController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:teacher');
    }

    public function index()
    {
        $students = Student::all();
        $subjects = Subject::all();
        $records = SlowLearner::with(['student', 'subject'])->orderBy('diagnostic_date', 'desc')->get();

        return view('teacher.remedial.index', compact('students', 'subjects', 'records'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'subject_id' => 'required|exists:subjects,id',
            'diagnostic_date' => 'required|date',
            'remedial_notes' => 'required|string|max:1000',
            'progress_status' => 'required|in:improving,stagnant',
        ]);

        SlowLearner::create($request->all());

        return redirect()->route('teacher.remedial.index')->with('success', 'Slow learner diagnostic entry logged.');
    }
}
