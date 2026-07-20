<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\PtmMeeting;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ParentPtmController extends Controller
{
    public function __construct()
    {
        $this->middleware('parent.auth');
    }

    public function index()
    {
        $parent = Auth::guard('parent')->user();
        $student = $parent->student;

        if (!$student) {
            abort(404, 'Student profile not found.');
        }

        $meetings = PtmMeeting::where('parent_id', $parent->id)->with('teacher')->get();
        $teachers = Teacher::all();

        return view('parent.ptm.index', compact('meetings', 'teachers', 'student'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'meeting_date' => 'required|date|after_or_equal:today',
            'time_slot' => 'required|string',
            'notes' => 'nullable|string|max:1000',
        ]);

        $parent = Auth::guard('parent')->user();

        PtmMeeting::create(array_merge($request->all(), [
            'parent_id' => $parent->id,
            'status' => 'requested',
        ]));

        return redirect()->route('parent.ptm.index')->with('success', 'PTM meeting slot requested successfully.');
    }
}
