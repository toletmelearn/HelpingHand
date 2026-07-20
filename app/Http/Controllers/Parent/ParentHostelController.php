<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\StudentHostelAllocation;
use Illuminate\Support\Facades\Auth;

class ParentHostelController extends Controller
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
            abort(404, 'Linked student profile not found.');
        }

        $allocation = StudentHostelAllocation::with(['room.hostel'])
            ->where('student_id', $student->id)
            ->first();

        return view('parent.hostels.index', compact('allocation', 'student'));
    }
}
