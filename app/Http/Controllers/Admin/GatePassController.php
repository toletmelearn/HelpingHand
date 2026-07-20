<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GatePass;
use App\Models\Student;
use App\Models\User;
use App\Services\FrontOffice\FrontOfficeService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class GatePassController extends Controller
{
    protected FrontOfficeService $service;

    public function __construct(FrontOfficeService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $query = GatePass::with(['student', 'user', 'requester', 'approver', 'verifier']);

        if ($request->filled('pass_type')) {
            $query->where('pass_type', $request->pass_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $query->whereDate('request_date', $request->date);
        }

        $passes = $query->latest('request_date')->latest('departure_time')->paginate(15)->withQueryString();

        return view('admin.front-office.gate-passes.index', compact('passes'));
    }

    public function create(Request $request)
    {
        $selectedStudent = null;
        if (old('student_id')) {
            $selectedStudent = Student::with('schoolClass')->find(old('student_id'));
        }

        $selectedStaff = null;
        if (old('user_id')) {
            $selectedStaff = User::find(old('user_id'));
        }

        return view('admin.front-office.gate-passes.create', compact('selectedStudent', 'selectedStaff'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pass_type' => 'required|in:student,staff,visitor,vehicle',
            'holder_name' => 'required|string|max:255',
            'student_id' => 'nullable|required_if:pass_type,student|exists:students,id',
            'user_id' => 'nullable|required_if:pass_type,staff|exists:users,id',
            'vehicle_no' => 'nullable|string|max:50',
            'purpose' => 'required|string|max:500',
            'request_date' => 'required|date',
            'departure_time' => 'required',
            'status' => 'required|in:pending,approved,rejected,active,completed',
            'requested_by' => 'nullable|exists:users,id',
            'approved_by' => 'nullable|exists:users,id',
            'exit_gate' => 'nullable|string|max:100',
        ]);

        $validated['requested_by'] = $validated['requested_by'] ?? Auth::id();

        // Let the service check for duplicate active student passes and save
        $gatePass = $this->service->createGatePass($validated);

        return redirect()->route('admin.front-office.gate-passes.index')->with('success', 'Gate pass created successfully.');
    }

    public function show($id)
    {
        $pass = GatePass::with(['student', 'user', 'requester', 'approver', 'verifier'])->findOrFail($id);
        return view('admin.front-office.gate-passes.show', compact('pass'));
    }

    public function edit(Request $request, $id)
    {
        $pass = GatePass::findOrFail($id);

        $selectedStudent = null;
        $studentId = old('student_id', $pass->student_id);
        if ($studentId) {
            $selectedStudent = Student::with('schoolClass')->find($studentId);
        }

        $selectedStaff = null;
        $staffId = old('user_id', $pass->user_id);
        if ($staffId) {
            $selectedStaff = User::find($staffId);
        }

        return view('admin.front-office.gate-passes.edit', compact('pass', 'selectedStudent', 'selectedStaff'));
    }

    public function update(Request $request, $id)
    {
        $pass = GatePass::findOrFail($id);

        $validated = $request->validate([
            'pass_type' => 'required|in:student,staff,visitor,vehicle',
            'holder_name' => 'required|string|max:255',
            'student_id' => 'nullable|required_if:pass_type,student|exists:students,id',
            'user_id' => 'nullable|required_if:pass_type,staff|exists:users,id',
            'vehicle_no' => 'nullable|string|max:50',
            'purpose' => 'required|string|max:500',
            'request_date' => 'required|date',
            'departure_time' => 'required',
            'status' => 'required|in:pending,approved,rejected,active,completed',
            'requested_by' => 'nullable|exists:users,id',
            'approved_by' => 'nullable|exists:users,id',
            'exit_gate' => 'nullable|string|max:100',
        ]);

        $pass->update($validated);

        return redirect()->route('admin.front-office.gate-passes.index')->with('success', 'Gate pass updated successfully.');
    }

    public function destroy($id)
    {
        $pass = GatePass::findOrFail($id);
        $pass->delete();

        return redirect()->route('admin.front-office.gate-passes.index')->with('success', 'Gate pass deleted successfully.');
    }

    public function verifyExit(Request $request, $id)
    {
        $pass = GatePass::findOrFail($id);

        $pass->update([
            'status' => 'completed',
            'arrival_time' => Carbon::now()->format('H:i:s'),
            'verified_by' => Auth::id(),
            'exit_gate' => $request->get('exit_gate', $pass->exit_gate),
            'override_reason' => $request->get('override_reason', $pass->override_reason),
        ]);

        return back()->with('success', 'Gate pass exit verified and completed.');
    }

    public function gatekeeper(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::today()->toDateString();
        
        // Find if this guard has any active assignment for today
        $currentAssignment = \App\Models\GuardDutyAssignment::where('user_id', $user->id)
            ->whereDate('duty_date', $today)
            ->where('status', 'active')
            ->first();
            
        $myGate = $currentAssignment ? $currentAssignment->gate_name : null;

        // Fetch gate passes routed to this gate
        $myGatePasses = collect();
        if ($myGate) {
            $myGatePasses = GatePass::with(['student.schoolClass', 'user'])
                ->whereDate('request_date', $today)
                ->where('exit_gate', $myGate)
                ->whereIn('status', ['approved', 'active'])
                ->orderBy('departure_time')
                ->get();
        }

        // Global lookup (for lookup / override capability)
        $query = GatePass::with(['student.schoolClass', 'user'])
            ->whereDate('request_date', $today)
            ->whereIn('status', ['approved', 'active', 'pending']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('holder_name', 'like', "%{$search}%")
                  ->orWhere('vehicle_no', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }
        
        $globalPasses = $request->filled('search') ? $query->get() : collect();

        // Recently verified at this gate
        $verifiedPasses = GatePass::with(['student.schoolClass', 'user'])
            ->whereDate('request_date', $today)
            ->where('status', 'completed')
            ->when($myGate, function($q) use ($myGate) {
                $q->where('exit_gate', $myGate);
            })
            ->latest('updated_at')
            ->take(10)
            ->get();

        return view('admin.front-office.gate-passes.gatekeeper', compact('myGatePasses', 'globalPasses', 'verifiedPasses', 'myGate', 'currentAssignment'));
    }

    public function requestGateChange(Request $request, $id)
    {
        $pass = GatePass::findOrFail($id);
        $validated = $request->validate([
            'new_gate' => 'required|string|max:100',
        ]);
        
        $pass->update([
            'exit_gate' => $validated['new_gate'],
        ]);
        
        return response()->json(['success' => true, 'message' => 'Gate pass exit gate re-routed successfully.']);
    }

    public function searchStudents(Request $request)
    {
        $search = $request->get('q');
        $students = Student::with('schoolClass')
            ->where(function($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('admission_no', 'like', "%{$search}%")
                      ->orWhere('aadhar_number', 'like', "%{$search}%")
                      ->orWhere('father_name', 'like', "%{$search}%");
            })
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'text' => $s->name . ' (Adm: ' . $s->admission_no . ', Class: ' . ($s->schoolClass ? $s->schoolClass->name : 'N/A') . ', Father: ' . $s->father_name . ')',
                    'name' => $s->name
                ];
            });
        return response()->json($students);
    }

    public function searchStaff(Request $request)
    {
        $search = $request->get('q');
        $staff = User::where(function($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            })
            ->whereHas('roles', function($q) {
                $q->whereIn('name', ['admin', 'super-admin', 'teacher', 'clerk', 'accountant', 'receptionist']);
            })
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'text' => $u->name . ' (Email: ' . $u->email . ')',
                    'name' => $u->name
                ];
            });

        return response()->json($staff);
    }
}
