<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TeacherAttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        // Filter by date
        $date = $request->date ?? now()->toDateString();
        
        // Automatically create present records for all teachers if not exists
        $this->ensureAllTeachersPresent($date);
        
        $query = Teacher::with(['teacherAttendances' => function($q) use ($date) {
            $q->whereDate('date', $date);
        }]);

        // Filter by status
        if ($request->filled('status')) {
            $query->whereHas('teacherAttendances', function($q) use ($request, $date) {
                $q->whereDate('date', $date)
                  ->where('status', $request->status);
            });
        }

        $teachers = $query->orderBy('id')->paginate(50);
        
        // Get attendance statistics
        $stats = $this->getAttendanceStats($date);
        
        // Calculate weekend indicator
        $selectedDate = \Carbon\Carbon::parse($date);
        $isSunday = $selectedDate->isSunday();
        $allPresent = $teachers->filter(function($teacher) {
            return $teacher->teacherAttendances->first() && $teacher->teacherAttendances->first()->status === 'present';
        })->count() == $teachers->count() && $teachers->count() > 0;

        // Get status options
        $statuses = ['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'half_day' => 'Half Day'];

        return view('admin.teacher-attendance.index', compact('teachers', 'date', 'stats', 'statuses', 'isSunday', 'allPresent'));
    }

    public function create()
    {
        $date = now()->toDateString();
        $teachers = Teacher::with(['teacherAttendances' => function($q) use ($date) {
            $q->whereDate('date', $date);
        }])->orderBy('id')->get();
        
        $statuses = ['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'half_day' => 'Half Day'];
        
        return view('admin.teacher-attendance.create', compact('teachers', 'date', 'statuses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'teacher_ids' => 'required|array',
            'teacher_ids.*' => 'exists:teachers,id',
            'statuses' => 'required|array',
            'statuses.*' => 'in:present,absent,late,half_day',
            'remarks.*' => 'nullable|string|max:255'
        ]);

        $date = $request->date;
        $attendances = [];

        foreach ($request->teacher_ids as $index => $teacherId) {
            // Check if attendance already exists for this teacher on this date
            $existing = TeacherAttendance::where('teacher_id', $teacherId)
                ->whereDate('date', $date)
                ->first();

            if ($existing) {
                // Update existing record
                $existing->update([
                    'status' => $request->statuses[$index],
                    'remarks' => $request->remarks[$index] ?? null,
                    'updated_by' => Auth::id()
                ]);
            } else {
                // Create new record
                $attendances[] = [
                    'teacher_id' => $teacherId,
                    'date' => $date,
                    'status' => $request->statuses[$index],
                    'remarks' => $request->remarks[$index] ?? null,
                    'marked_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        // Insert new records
        if (!empty($attendances)) {
            TeacherAttendance::insert($attendances);
        }

        return redirect()->route('admin.teacher-attendance.index', ['date' => $date])
            ->with('success', 'Teacher attendance marked successfully!');
    }

    public function show($teacherId, Request $request)
    {
        $teacher = Teacher::findOrFail($teacherId);
        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;
        
        $attendances = TeacherAttendance::where('teacher_id', $teacherId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date', 'desc')
            ->get();
            
        $stats = $this->getTeacherMonthlyStats($teacherId, $month, $year);

        return view('admin.teacher-attendance.show', compact('teacher', 'attendances', 'stats', 'month', 'year'));
    }

    public function reports(Request $request)
    {
        $date = $request->date ?? now()->toDateString();
        $teachers = Teacher::with(['teacherAttendances' => function($q) use ($date) {
            $q->whereDate('date', $date);
        }])->orderBy('id')->get();
        
        $stats = $this->getAttendanceStats($date);

        return view('admin.teacher-attendance.reports', compact('teachers', 'date', 'stats'));
    }

    public function markAllPresent(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);
    
        $date = $request->date;
            
        // Check if it's Sunday (0 = Sunday, 1 = Monday, ..., 6 = Saturday)
        if (date('w', strtotime($date)) == 0) {
            return redirect()->back()
                ->with('error', 'Cannot mark all teachers as present on Sunday!');
        }
            
        // Get all teachers
        $teachers = Teacher::all();
            
        $attendances = [];
        $timestamp = now();
            
        foreach ($teachers as $teacher) {
            // Check if attendance already exists for this teacher on this date
            $existing = TeacherAttendance::where('teacher_id', $teacher->id)
                ->whereDate('date', $date)
                ->first();
    
            if ($existing) {
                // Update existing record to present
                $existing->update([
                    'status' => 'present',
                    'remarks' => 'Auto-marked as present',
                    'updated_by' => Auth::id()
                ]);
            } else {
                // Create new present record
                $attendances[] = [
                    'teacher_id' => $teacher->id,
                    'date' => $date,
                    'status' => 'present',
                    'remarks' => 'Auto-marked as present',
                    'marked_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp
                ];
            }
        }
    
        // Insert new records
        if (!empty($attendances)) {
            TeacherAttendance::insert($attendances);
        }
    
        return redirect()->route('admin.teacher-attendance.index', ['date' => $date])
            ->with('success', 'All teachers marked as present for ' . date('F j, Y', strtotime($date)) . '!');
    }
    
    /**
     * Update individual teacher attendance status
     */
    public function updateAttendance(Request $request, $teacherId)
    {
        $request->validate([
            'date' => 'required|date',
            'status' => 'required|in:present,absent,late,half_day',
            'remarks' => 'nullable|string|max:255'
        ]);
    
        $date = $request->date;
        $status = $request->status;
        $remarks = $request->remarks;
    
        // Check if it's Sunday
        if (date('w', strtotime($date)) == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update attendance on Sunday!'
            ], 400);
        }
    
        // Find or create attendance record
        $attendance = TeacherAttendance::firstOrNew([
            'teacher_id' => $teacherId,
            'date' => $date
        ]);
    
        $attendance->status = $status;
        $attendance->remarks = $remarks ?? ($status == 'present' ? 'Manually marked present' : 'Manually marked ' . $status);
        $attendance->updated_by = Auth::id();
            
        if (!$attendance->exists) {
            $attendance->marked_by = Auth::id();
            $attendance->created_at = now();
        }
            
        $attendance->updated_at = now();
        $attendance->save();
    
        return response()->json([
            'success' => true,
            'message' => 'Attendance updated successfully!',
            'attendance' => $attendance
        ]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'format' => 'required|in:xlsx,pdf,csv',
            'date' => 'nullable|date',
        ]);

        $format = $request->input('format', 'xlsx');
        $date = $request->input('date', now()->toDateString());
        
        $teachers = Teacher::with(['teacherAttendances' => function($q) use ($date) {
            $q->whereDate('date', $date);
        }])->orderBy('name')->get();

        // Create array of data
        $data = [];
        foreach ($teachers as $teacher) {
            $attendance = $teacher->teacherAttendances->first();
            $data[] = [
                'Teacher ID' => $teacher->id,
                'Name' => $teacher->name,
                'Email' => $teacher->email ?? 'N/A',
                'Department' => $teacher->department ?? 'N/A',
                'Subject' => $teacher->subject_specialization ?? 'N/A',
                'Status' => $attendance ? $attendance->getStatusText() : 'Not Marked',
                'Check-in Time' => $attendance ? $attendance->created_at->format('h:i A') : '-',
                'Remarks' => $attendance ? $attendance->remarks : '-',
                'Date' => $date,
            ];
        }

        $fileName = 'teacher_attendance_' . date('Y-m-d_H-i-s');
        
        // Create a temporary collection for export
        $collection = collect($data);
        
        if ($format === 'xlsx') {
            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\TeachersAttendanceExport($collection), $fileName . '.xlsx');
        } elseif ($format === 'csv') {
            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\TeachersAttendanceExport($collection), $fileName . '.csv');
        } else { // pdf
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.teacher-attendance.export-pdf', ['data' => $data, 'date' => $date]);
            return $pdf->download($fileName . '.pdf');
        }
    }

    private function getAttendanceStats($date)
    {
        $totalTeachers = Teacher::count();
        $present = TeacherAttendance::whereDate('date', $date)->where('status', 'present')->count();
        $absent = TeacherAttendance::whereDate('date', $date)->where('status', 'absent')->count();
        $late = TeacherAttendance::whereDate('date', $date)->where('status', 'late')->count();
        $halfDay = TeacherAttendance::whereDate('date', $date)->where('status', 'half_day')->count();
        
        return [
            'total' => $totalTeachers,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'half_day' => $halfDay,
            'attendance_rate' => $totalTeachers > 0 ? round(($present / $totalTeachers) * 100, 2) : 0
        ];
    }
    
    /**
     * Ensure all teachers have present attendance records for the given date
     */
    private function ensureAllTeachersPresent($date)
    {
        $teachers = Teacher::all();
        $attendances = [];
        $userId = Auth::id();
        
        foreach ($teachers as $teacher) {
            // Check if attendance already exists for this teacher on this date
            $existing = TeacherAttendance::where('teacher_id', $teacher->id)
                ->whereDate('date', $date)
                ->first();
            
            if (!$existing) {
                // Create present record
                $attendances[] = [
                    'teacher_id' => $teacher->id,
                    'date' => $date,
                    'status' => 'present',
                    'remarks' => 'Auto-marked as present',
                    'marked_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }
        
        // Bulk insert new attendance records
        if (!empty($attendances)) {
            TeacherAttendance::insert($attendances);
        }
    }

    private function getTeacherMonthlyStats($teacherId, $month, $year)
    {
        $attendances = TeacherAttendance::where('teacher_id', $teacherId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get();
            
        $totalDays = $attendances->count();
        $present = $attendances->where('status', 'present')->count();
        $late = $attendances->where('status', 'late')->count();
        $halfDay = $attendances->where('status', 'half_day')->count();
        
        return [
            'total_days' => $totalDays,
            'present' => $present,
            'late' => $late,
            'half_day' => $halfDay,
            'absent' => $totalDays - ($present + $late + $halfDay),
            'attendance_rate' => $totalDays > 0 ? round((($present + ($late * 0.75) + ($halfDay * 0.5)) / $totalDays) * 100, 2) : 0
        ];
    }
}