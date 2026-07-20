<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuardDutyAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class GuardDutyController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today()->toDateString();
        $date = $request->get('date', $today);

        // Fetch assignments for the selected date
        $assignments = GuardDutyAssignment::with(['guardUser', 'assigner'])
            ->whereDate('duty_date', $date)
            ->latest()
            ->get();

        // Get guards (users with role "guard" or "admin"/"super-admin")
        $guards = User::whereHas('roles', function($q) {
            $q->whereIn('name', ['guard', 'admin', 'super-admin', 'receptionist', 'clerk']);
        })->orderBy('name')->get();

        return view('admin.front-office.gate-passes.duty_assignment', compact('assignments', 'guards', 'date'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'gate_name' => 'required|string|max:100',
            'duty_date' => 'required|date',
            'shift' => 'required|string|in:Morning,Evening,Night,General',
        ]);

        // End any active assignment for this gate on the same date and shift to prevent conflicts
        GuardDutyAssignment::whereDate('duty_date', $validated['duty_date'])
            ->where('gate_name', $validated['gate_name'])
            ->where('shift', $validated['shift'])
            ->update(['status' => 'completed']);

        // Create new assignment
        GuardDutyAssignment::create(array_merge($validated, [
            'assigned_by' => Auth::id(),
            'status' => 'active',
        ]));

        return redirect()->back()->with('success', 'Guard assigned to gate successfully.');
    }

    public function destroy($id)
    {
        $assignment = GuardDutyAssignment::findOrFail($id);
        $assignment->delete();

        return redirect()->back()->with('success', 'Duty assignment removed successfully.');
    }
}
