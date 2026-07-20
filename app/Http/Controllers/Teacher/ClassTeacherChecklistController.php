<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\UniformCheck;
use Illuminate\Http\Request;

class ClassTeacherChecklistController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:teacher');
    }

    public function index()
    {
        $students = Student::all();
        $checks = UniformCheck::with('student')->orderBy('check_date', 'desc')->get();

        return view('teacher.uniform.index', compact('students', 'checks'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'check_date' => 'required|date',
            'is_compliant' => 'required|boolean',
            'remarks' => 'nullable|string|max:255',
        ]);

        UniformCheck::create($request->all());

        return redirect()->route('teacher.uniform.index')->with('success', 'Uniform compliance check logged successfully.');
    }
}
