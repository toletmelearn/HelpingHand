<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\StudentLeave;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeacherStudentLeaveController extends Controller
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
        $leaves = StudentLeave::with('student')->orderBy('created_at', 'desc')->get();

        return view('teacher.student-leaves.index', compact('leaves', 'teacher'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $teacher = $this->getTeacher();
        $leave = StudentLeave::findOrFail($id);

        $leave->update([
            'status' => $request->status,
            'approved_by' => $teacher->id,
        ]);

        return redirect()->route('teacher.student-leaves.index')->with('success', 'Student leave status updated.');
    }
}
