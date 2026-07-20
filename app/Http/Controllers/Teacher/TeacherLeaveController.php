<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TeacherLeave;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TeacherLeaveController extends Controller
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
        if (!$teacher) {
            abort(404, 'Teacher profile not found.');
        }

        $leaves = TeacherLeave::where('teacher_id', $teacher->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('teacher.leaves.index', compact('leaves', 'teacher'));
    }

    public function create()
    {
        return view('teacher.leaves.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'leave_type' => 'required|string|max:255',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
        ]);

        $teacher = $this->getTeacher();
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher profile not found.');
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $days = $startDate->diffInDays($endDate) + 1;

        TeacherLeave::create([
            'teacher_id' => $teacher->id,
            'leave_type' => $request->leave_type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'days' => $days,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return redirect()->route('teacher.leaves.index')
            ->with('success', 'Leave request submitted successfully.');
    }
}
