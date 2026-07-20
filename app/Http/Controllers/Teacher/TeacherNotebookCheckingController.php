<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\NotebookCheck;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeacherNotebookCheckingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:teacher');
    }

    private function getTeacher()
    {
        $teacherLogin = Auth::guard('teacher')->user();
        return Teacher::where('id', $teacherLogin->teacher_id)->first();
    }

    public function index()
    {
        $teacher = $this->getTeacher();
        $checks = NotebookCheck::where('checked_by', $teacher->id)->with(['student', 'subject'])->get();
        $students = Student::all();
        $subjects = Subject::all();

        return view('teacher.notebooks.index', compact('checks', 'students', 'subjects'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'subject_id' => 'required|exists:subjects,id',
            'check_date' => 'required|date',
            'deficiencies' => 'nullable|string',
            'recheck_date' => 'nullable|date|after_or_equal:check_date',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $teacher = $this->getTeacher();

        NotebookCheck::create(array_merge($request->all(), [
            'checked_by' => $teacher->id,
            'is_signed' => true,
        ]));

        return redirect()->route('teacher.notebooks.index')->with('success', 'Notebook check record submitted.');
    }
}
