<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeacherLeave;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminTeacherLeaveController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = TeacherLeave::with('teacher');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $leaves = $query->orderBy('created_at', 'desc')->paginate(15);

        return view('admin.hr.leaves.index', compact('leaves'));
    }

    public function show($id)
    {
        $leave = TeacherLeave::with(['teacher', 'approver'])->findOrFail($id);
        return view('admin.hr.leaves.show', compact('leave'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'approval_notes' => 'nullable|string|max:1000',
        ]);

        $leave = TeacherLeave::findOrFail($id);
        $leave->update([
            'status' => $request->status,
            'approval_notes' => $request->approval_notes,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return redirect()->route('admin.leaves.index')
            ->with('success', 'Leave request updated successfully.');
    }
}
