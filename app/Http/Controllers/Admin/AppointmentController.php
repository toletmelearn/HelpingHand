<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Guardian;
use App\Services\FrontOffice\FrontOfficeService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AppointmentController extends Controller
{
    protected FrontOfficeService $service;

    public function __construct(FrontOfficeService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $query = Appointment::with(['teacher', 'guardian', 'receptionist']);

        if ($request->filled('date')) {
            $query->whereDate('scheduled_date', $request->date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        $appointments = $query->latest('scheduled_date')->latest('start_time')->paginate(15)->withQueryString();
        
        $teachers = User::whereHas('roles', function($q) {
            $q->whereIn('name', ['admin', 'super-admin', 'teacher']);
        })->orderBy('name')->get();

        return view('admin.front-office.appointments.index', compact('appointments', 'teachers'));
    }

    public function create(Request $request)
    {
        $selectedGuardian = null;
        if (old('guardian_id')) {
            $selectedGuardian = Guardian::find(old('guardian_id'));
        }

        $selectedTeacher = null;
        if (old('teacher_id')) {
            $selectedTeacher = User::find(old('teacher_id'));
        }

        return view('admin.front-office.appointments.create', compact('selectedGuardian', 'selectedTeacher'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'visitor_name' => 'required|string|max:255',
            'guardian_id' => 'nullable|exists:guardians,id',
            'teacher_id' => 'required|exists:users,id',
            'scheduled_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'purpose' => 'required|string|max:255',
            'status' => 'required|in:pending,approved,rejected,completed,no_show',
            'feedback' => 'nullable|string',
        ]);

        // Overlap verification
        $overlap = $this->service->checkTeacherOverlaps(
            $validated['teacher_id'],
            $validated['scheduled_date'],
            $validated['start_time'],
            $validated['end_time']
        );

        if ($overlap) {
            return back()->withInput()->withErrors([
                'start_time' => 'The selected teacher already has an overlapping appointment at this slot on this date.'
            ]);
        }

        Appointment::create(array_merge($validated, [
            'receptionist_id' => Auth::id(),
        ]));

        return redirect()->route('admin.front-office.appointments.index')->with('success', 'Appointment scheduled successfully.');
    }

    public function show($id)
    {
        $appointment = Appointment::with(['teacher', 'guardian', 'receptionist'])->findOrFail($id);
        return view('admin.front-office.appointments.show', compact('appointment'));
    }

    public function edit(Request $request, $id)
    {
        $appointment = Appointment::with(['teacher', 'guardian'])->findOrFail($id);

        $selectedGuardian = null;
        $guardianId = old('guardian_id', $appointment->guardian_id);
        if ($guardianId) {
            $selectedGuardian = Guardian::find($guardianId);
        }

        $selectedTeacher = null;
        $teacherId = old('teacher_id', $appointment->teacher_id);
        if ($teacherId) {
            $selectedTeacher = User::find($teacherId);
        }

        return view('admin.front-office.appointments.edit', compact('appointment', 'selectedGuardian', 'selectedTeacher'));
    }

    public function searchGuardians(Request $request)
    {
        $search = $request->get('q');
        $guardians = Guardian::where('name', 'like', "%{$search}%")
            ->orWhere('phone', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%")
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($g) {
                return [
                    'id' => $g->id,
                    'text' => $g->name . ' (Phone: ' . $g->phone . ', Email: ' . ($g->email ?? 'N/A') . ')'
                ];
            });

        return response()->json($guardians);
    }

    public function searchTeachers(Request $request)
    {
        $search = $request->get('q');
        $teachers = User::where(function($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            })
            ->whereHas('roles', function($q) {
                $q->whereIn('name', ['admin', 'super-admin', 'teacher']);
            })
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'text' => $u->name . ' (Email: ' . $u->email . ')'
                ];
            });

        return response()->json($teachers);
    }

    public function update(Request $request, $id)
    {
        $appointment = Appointment::findOrFail($id);

        $validated = $request->validate([
            'visitor_name' => 'required|string|max:255',
            'guardian_id' => 'nullable|exists:guardians,id',
            'teacher_id' => 'required|exists:users,id',
            'scheduled_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'purpose' => 'required|string|max:255',
            'status' => 'required|in:pending,approved,rejected,completed,no_show',
            'feedback' => 'nullable|string',
        ]);

        // Clean time format to H:i
        $validated['start_time'] = Carbon::parse($validated['start_time'])->format('H:i');
        $validated['end_time'] = Carbon::parse($validated['end_time'])->format('H:i');

        // Overlap verification
        $overlap = $this->service->checkTeacherOverlaps(
            $validated['teacher_id'],
            $validated['scheduled_date'],
            $validated['start_time'],
            $validated['end_time'],
            $id
        );

        if ($overlap) {
            return back()->withInput()->withErrors([
                'start_time' => 'The selected teacher already has an overlapping appointment at this slot on this date.'
            ]);
        }

        $appointment->update($validated);

        return redirect()->route('admin.front-office.appointments.index')->with('success', 'Appointment updated successfully.');
    }

    public function destroy($id)
    {
        $appointment = Appointment::findOrFail($id);
        $appointment->delete();

        return redirect()->route('admin.front-office.appointments.index')->with('success', 'Appointment cancelled successfully.');
    }

    public function updateStatus(Request $request, $id)
    {
        $appointment = Appointment::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected,completed,no_show',
            'feedback' => 'nullable|string',
        ]);

        $appointment->update($validated);

        return back()->with('success', 'Appointment status updated.');
    }
}
