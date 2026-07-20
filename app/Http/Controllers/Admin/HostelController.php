<?php
 
namespace App\Http\Controllers\Admin;
 
use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\HostelRoom;
use App\Models\Student;
use App\Models\StudentHostelAllocation;
use Illuminate\Http\Request;
 
class HostelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $hostels = Hostel::with('rooms')->get();
        $rooms = HostelRoom::with(['hostel', 'allocations.student'])->get();
        $students = Student::all();
 
        // Calculate occupancy totals
        $totalCapacity = HostelRoom::sum('capacity');
        $occupiedBeds = StudentHostelAllocation::where('status', 'active')->count();
        $vacantBeds = max(0, $totalCapacity - $occupiedBeds);
 
        $allocations = StudentHostelAllocation::with(['student', 'room.hostel'])
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get();
 
        return view('admin.hostels.dashboard', compact(
            'hostels',
            'rooms',
            'students',
            'totalCapacity',
            'occupiedBeds',
            'vacantBeds',
            'allocations'
        ));
    }
 
    public function storeHostel(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:boys,girls,coed',
            'capacity' => 'required|integer|min:1',
        ]);
 
        Hostel::create($request->all());
 
        return redirect()->route('admin.hostels.index')->with('success', 'Hostel created successfully.');
    }
 
    public function storeRoom(Request $request)
    {
        $request->validate([
            'hostel_id' => 'required|exists:hostels,id',
            'room_no' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1',
            'cost_per_bed' => 'required|numeric|min:0',
        ]);
 
        HostelRoom::create($request->all());
 
        return redirect()->route('admin.hostels.index')->with('success', 'Room added successfully.');
    }

    public function allocateBed(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'room_id' => 'required|exists:hostel_rooms,id',
        ]);
 
        $room = HostelRoom::with('hostel')->findOrFail($validated['room_id']);
        $student = Student::findOrFail($validated['student_id']);
 
        // Check if student already has an active allocation
        $existingAllocation = StudentHostelAllocation::where('student_id', $validated['student_id'])
            ->where('status', 'active')
            ->first();
 
        if ($existingAllocation) {
            return back()->with('error', 'Student is already allocated to a hostel room.');
        }
 
        // Room capacity check
        $activeAllocations = StudentHostelAllocation::where('room_id', $validated['room_id'])
            ->where('status', 'active')
            ->count();
 
        if ($activeAllocations >= $room->capacity) {
            return back()->with('error', 'Selected room is already fully occupied.');
        }
 
        // Gender alignment check
        $hostelType = strtolower($room->hostel->type);
        $studentGender = strtolower($student->gender); // boys/girls vs male/female
 
        if ($hostelType === 'boys' && $studentGender === 'female') {
            return back()->with('error', 'Cannot allocate a female student to a boys hostel.');
        }
 
        if ($hostelType === 'girls' && $studentGender === 'male') {
            return back()->with('error', 'Cannot allocate a male student to a girls hostel.');
        }
 
        StudentHostelAllocation::create([
            'student_id' => $validated['student_id'],
            'room_id' => $validated['room_id'],
            'status' => 'active',
        ]);
 
        return back()->with('success', 'Student allocated to hostel room successfully.');
    }
 
    public function vacateBed($id)
    {
        $allocation = StudentHostelAllocation::findOrFail($id);
        if ($allocation->status === 'vacated') {
            return back()->with('error', 'Room bed allocation is already vacated.');
        }
 
        $allocation->update([
            'status' => 'vacated',
        ]);
 
        return back()->with('success', 'Hostel bed vacated successfully.');
    }
}
