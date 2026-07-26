<?php

namespace App\Http\Controllers;

use App\Models\AcademicEvent;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\Attendance\AttendanceClassResolver;
use App\Services\Attendance\AttendanceBulkPreflightService;
use App\Support\Attendance\AttendancePeriodPresenter;
use App\Support\Attendance\AttendanceCreditCalculator;

class AttendanceController extends Controller
{
    private const EXPORT_STATUS_FILTERS = ['present', 'absent', 'late', 'half_day'];

    /**
     * Display a listing of attendance records.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Attendance::class);
        $query = Attendance::with(['student', 'teacher', 'markedBy']);
        
        // Filter by date
        if ($request->filled('date')) {
            $query->where('date', $request->date);
        }
        
        // Filter by class
        if ($request->filled('class')) {
            $query->where('class', $request->class);
        }
        
        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        $attendances = $query->latest()->paginate(20);
        
        // Get unique classes for filter dropdown
        $classes = Student::distinct()->pluck('class')->filter()->sortBy('class');
        
        // Calculate statistics for dashboard
        $stats = $this->calculateAttendanceStats($request->date);
        
        return view('attendance.index', compact('attendances', 'classes', 'stats'));
    }

    /**
     * Calculate attendance statistics
     */
    private function calculateAttendanceStats($date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        
        // Total students
        $totalStudents = Student::count();
        
        // Retrieve status records for calculator summarization
        $records = Attendance::whereDate('date', $date)->get(['status']);
        
        $summary = AttendanceCreditCalculator::summarizeRecords($records, 'status');
        
        return [
            'total_students' => $totalStudents,
            'present_today' => $summary['present_days'],
            'attendance_rate' => $summary['attendance_rate'],
            'attendance_credit' => $summary['attendance_credit'],
            'absent' => $summary['absent_days'],
            'late' => $summary['late_days'],
            'half_day' => $summary['half_days'],
            'leave' => $summary['leave_days'],
        ];
    }
    
    /**
     * Ensure all students in a class have present attendance records for the given date
     */
    private function ensureAllStudentsPresent($class, $date)
    {
        // WRITE HELPER: This method performs bulk inserts to ensure all students
        // in a class have attendance records for a given date. It must not be
        // invoked from GET/read routes. Attendance writes should go through
        // explicit store or preflight/apply flows.

        // Check if any attendance already exists for this class and date
        if (Attendance::isMarked($class, $date)) {
            return; // Already marked, don't override
        }

        // Get all students in the class
        $students = Student::where('class', $class)->get();

        if ($students->count() > 0) {
            $attendances = [];
            $userId = Auth::id();
            $session = date('Y') . '-' . (date('Y') + 1);
            $timestamp = now();

            foreach ($students as $student) {
                $attendances[] = [
                    'student_id' => $student->id,
                    'date' => $date,
                    'status' => 'present',
                    'remarks' => 'Auto-marked as present',
                    'period' => null,
                    'subject' => 'General',
                    'class' => $class,
                    'session' => $session,
                    'marked_by' => $userId,
                    'ip_address' => request()->ip(),
                    'device_info' => request()->userAgent(),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp
                ];
            }

            // Bulk insert new attendance records
            if (!empty($attendances)) {
                Attendance::insert($attendances);
            }
        }
    }

    /**
     * Show the form for marking daily attendance.
     */
    public function create(Request $request)
    {
        $this->authorize('create', Attendance::class);
        $date = $request->date ?? now()->toDateString();
        $class = $request->class;
        
        if (!$class) {
            // Get all classes if none selected
            $classes = Student::distinct()->pluck('class')->filter()->sortBy('class');
            return view('attendance.select_class', compact('classes', 'date'));
        }
        
        // Check if attendance is already marked for this class and date
        if (Attendance::isMarked($class, $date)) {
            return redirect()->route('attendance.index')
                ->with('warning', "Attendance for class $class on $date is already marked!");
        }
        
        // Phase 5E: create page must remain read-only; attendance writes go through
        // explicit store/preflight flows. Do NOT auto-mark or insert records here.
        
        // Get students in the selected class with their attendance
        $students = Student::where('class', $class)
            ->with(['attendances' => function($query) use ($date) {
                $query->where('date', $date);
            }])
            ->orderBy('roll_number')
            ->get();
            
        // Get subjects taught by teachers
        $subjects = Teacher::pluck('subject_specialization')->filter()->unique()->values();
        
        return view('attendance.create', compact('students', 'class', 'date', 'subjects'));
    }

    /**
     * Store attendance records for a class.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Attendance::class);
        // Handle bulk marking if classes are provided
        if ($request->filled('classes') && $request->filled('default_status')) {
            return back()->with(
                'warning',
                'Direct bulk attendance marking is temporarily disabled. Please use Preview first. Safe bulk apply is not enabled yet.'
            );

            $request->validate([
                'date' => 'required|date',
                'subject' => 'required|string',
                'period' => 'nullable|string',
                'classes' => 'required|array',
                'classes.*' => 'string',
                'default_status' => 'required|in:present,absent,late,half_day'
            ]);
            
            $totalMarked = 0;
            $errors = [];
            
            foreach ($request->classes as $class) {
                // Check if attendance is already marked for this class and date
                if (Attendance::isMarked($class, $request->date, $request->period)) {
                    $errors[] = "Attendance for class $class on " . $request->date . " is already marked!";
                    continue;
                }
                
                // Get students in the class
                $students = Student::where('class', $class)->get();
                
                if ($students->count() > 0) {
                    $attendances = [];
                    $timestamp = now();
                    
                    foreach ($students as $student) {
                        $attendances[] = [
                            'student_id' => $student->id,
                            'date' => $request->date,
                            'status' => $request->default_status,
                            'remarks' => null,
                            'period' => $request->period,
                            'subject' => $request->subject,
                            'class' => $class,
                            'session' => date('Y') . '-' . (date('Y') + 1),
                            'marked_by' => Auth::id(), // Current authenticated user
                            'ip_address' => $request->ip(),
                            'device_info' => $request->userAgent(),
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp
                        ];
                    }
                    
                    Attendance::insert($attendances);
                    $totalMarked += count($attendances);
                }
            }
            
            $message = "Successfully marked attendance for $totalMarked students.";
            if (!empty($errors)) {
                $message .= ' ' . implode(' ', $errors);
            }
            
            return redirect()->route('attendance.index')->with('success', $message);
        }
        
        // Handle individual marking
        $request->validate([
            'class' => 'required|string',
            'date' => 'required|date',
            'subject' => 'required|string',
            'period' => 'nullable|string',
            'student_ids' => 'required|array',
            'statuses' => 'required|array',
            'remarks.*' => 'nullable|string|max:255'
        ]);
        
        // Check if already marked
        if (Attendance::isMarked($request->class, $request->date, $request->period)) {
            return back()->with('error', 'Attendance for this class, date, and period is already marked!');
        }

        try {
            $holiday = AcademicEvent::isHoliday($request->date);
        } catch (\Throwable $e) {
            Log::warning('Failed to check holiday status while marking attendance: ' . $e->getMessage());
            $holiday = null;
        }

        if ($holiday) {
            return back()->with('error', "Attendance cannot be marked on a holiday: {$holiday->title}.");
        }
        
        $attendances = [];
        $timestamp = now();
        $classResolver = app(AttendanceClassResolver::class);
        $resolvedClasses = [];

        foreach ($request->student_ids as $studentId) {
            $student = Student::find($studentId);

            if (!$student) {
                return back()
                    ->withInput()
                    ->with('error', 'Student is not eligible for attendance marking.');
            }

            $classResolution = $classResolver->resolveForStudent($student);

            if (!$classResolution['ok']) {
                return back()
                    ->withInput()
                    ->with('error', $classResolution['message']);
            }

            $resolvedClasses[$studentId] = $classResolution['class'];
        }
        
        foreach ($request->student_ids as $index => $studentId) {
            $attendances[] = [
                'student_id' => $studentId,
                'date' => $request->date,
                'status' => $request->statuses[$index] ?? 'absent',
                'remarks' => $request->remarks[$index] ?? null,
                'period' => $request->period,
                'subject' => $request->subject,
                'class' => $resolvedClasses[$studentId],
                'session' => date('Y') . '-' . (date('Y') + 1),
                'marked_by' => Auth::id(), // Current authenticated user
                'ip_address' => $request->ip(),
                'device_info' => $request->userAgent(),
                'created_at' => $timestamp,
                'updated_at' => $timestamp
            ];
        }
        
        Attendance::insert($attendances);
        
        return redirect()->route('attendance.index')
            ->with('success', 'Attendance marked successfully for ' . count($attendances) . ' students!');
    }

    /**
     * Display the specified attendance record.
     */
    public function show(Attendance $attendance)
    {
        $this->authorize('view', $attendance);
        $attendance->load(['student', 'teacher', 'markedBy']);
        return view('attendance.show', compact('attendance'));
    }

    /**
     * Show the form for editing the specified attendance record.
     */
    public function edit(Attendance $attendance)
    {
        try {
            $this->authorize('update', $attendance);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return redirect()->route('attendance.index')->with('error', 'You do not have permission to edit this attendance record.');
        }
        
        $attendance->load(['student', 'teacher', 'markedBy']);
        
        // Get subjects taught by teachers
        $subjects = Teacher::pluck('subject_specialization')->filter()->unique()->values();
        
        return view('attendance.edit', compact('attendance', 'subjects'));
    }

    /**
     * Update the specified attendance record in storage.
     */
    public function update(Request $request, Attendance $attendance)
    {
        $this->authorize('update', $attendance);
        
        $request->validate([
            'status' => 'required|in:present,absent,late,half_day',
            'subject' => 'required|string',
            'remarks' => 'nullable|string|max:255',
        ]);
        
        // Phase 6T: ordinary update cannot mutate attendance class/date/period.
        $attendance->update([
            'status' => $request->status,
            'subject' => $request->subject,
            'remarks' => $request->remarks,
        ]);
        
        return redirect()->route('attendance.show', $attendance)
            ->with('success', 'Attendance record updated successfully!');
    }

    /**
     * Show attendance reports.
     */
    public function reports(Request $request)
    {
        try {
            $this->authorize('viewAny', Attendance::class);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return redirect()->route('login')->with('error', 'You need to be logged in to view attendance reports.');
        }
        
        $date = $request->date ?? now()->toDateString();
        $class = $request->class;
        
        if ($class) {
            $stats = Attendance::getAttendanceStats($date, $class);
            $attendances = Attendance::where('date', $date)
                ->where('class', $class)
                ->with('student')
                ->orderBy('student.roll_number')
                ->get();
        } else {
            $stats = Attendance::getAttendanceStats($date);
            $attendances = collect(); // Empty collection if no class selected
        }
        
        $classes = Student::distinct()->pluck('class')->filter()->sortBy('class');
        
        return view('attendance.reports', compact('attendances', 'stats', 'date', 'classes', 'class'));
    }

    /**
     * Get student attendance report.
     */
    public function studentReport($studentId, Request $request)
    {
        $student = Student::findOrFail($studentId);
        $this->authorize('view', $student);
        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;
        
        $report = Attendance::getStudentMonthlyReport($studentId, $month, $year);
        
        return view('attendance.student_report', compact('report', 'student'));
    }

    /**
     * Bulk attendance marking for multiple classes.
     */
    public function bulkMark(Request $request)
    {
        $this->authorize('create', Attendance::class);
        $classes = Student::distinct()->pluck('class')->filter()->sortBy('class');
        return view('attendance.bulk_mark', compact('classes'));
    }

    /**
     * Export attendance data.
     */
    public function export(Request $request)
    {
        try {
            $this->authorize('viewAny', Attendance::class);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return redirect()->route('login')->with('error', 'You need to be logged in to export attendance data.');
        }
        
        $query = Attendance::query();
        
        if ($request->filled('from_date')) {
            $query->where('date', '>=', $request->from_date);
        }
        
        if ($request->filled('to_date')) {
            $query->where('date', '<=', $request->to_date);
        }
        
        if ($request->filled('class')) {
            $query->where('class', $request->class);
        }

        if ($request->filled('status') && in_array($request->status, self::EXPORT_STATUS_FILTERS, true)) {
            $query->where('status', $request->status);
        }
        
        $attendances = $query->with(['student', 'markedBy'])->get();
        
        // Return CSV or Excel export
        return $this->exportToCsv($attendances);
    }

    /**
     * Export to CSV helper method.
     */
    private function exportToCsv($attendances)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="attendance-report-' . date('Y-m-d') . '.csv"',
        ];
        
        $callback = function() use ($attendances) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fwrite($file, "\xEF\xBB\xBF");
            
            // Headers
            fputcsv($file, [
                'Date', 'Class', 'Student Name', 'Roll Number', 'Status', 
                'Subject', 'Period', 'Period Display', 'Remarks', 'Marked By', 'IP Address'
            ]);
            
            // Data
            foreach ($attendances as $attendance) {
                fputcsv($file, [
                    $attendance->date->format('Y-m-d'),
                    $attendance->class,
                    $attendance->student->name ?? 'N/A',
                    $attendance->student->roll_number ?? 'N/A',
                    ucfirst($attendance->status),
                    $attendance->subject,
                    $attendance->period,
                    AttendancePeriodPresenter::display($attendance->period),
                    $attendance->remarks,
                    $attendance->markedBy->name ?? 'System',
                    $attendance->ip_address
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Remove the specified attendance record.
     */
    public function destroy(Attendance $attendance)
    {
        $this->authorize('delete', $attendance);
        
        // Phase 6V: hard delete disabled until audit-preserving attendance correction workflow exists.
        return redirect()->route('attendance.index')
            ->with(
                'warning',
                'Web attendance deletion is temporarily disabled. Use an audit-preserving correction workflow once enabled.'
            );
    }

    /**
     * Read-only preflight for bulk attendance payloads.
     */
    public function preflight(Request $request, AttendanceBulkPreflightService $preflightService)
    {
        $this->authorize('viewAny', Attendance::class);

        $payload = $request->only(['date', 'period', 'class_id', 'section_id', 'class', 'attendance_rows']);

        $result = $preflightService->preflight($payload);

        return response()->json([
            'success' => true,
            'data' => $result,
        ], 200);
    }

    /**
     * Render read-only preflight result view for bulk attendance forms.
     */
    public function preflightView(Request $request, AttendanceBulkPreflightService $preflightService)
    {
        $this->authorize('viewAny', Attendance::class);

        // Map incoming bulk form inputs into attendance_rows expected by the service
        $date = $request->input('date');
        $period = $request->input('period');
        $providedClass = $request->input('class');
        $providedClasses = $request->input('classes', []);
        $providedClassId = $request->input('class_id');
        $providedSectionId = $request->input('section_id');
        $defaultStatus = $request->input('default_status');

        $attendanceRows = [];

        // If explicit attendance_rows provided (detailed form), use them
        if ($request->has('attendance_rows')) {
            $attendanceRows = $request->input('attendance_rows');
        } else {
            // Expand classes[] selection into student-level rows using legacy class string
            if (!empty($providedClasses) && is_array($providedClasses)) {
                foreach ($providedClasses as $cls) {
                    $students = Student::where('class', $cls)->get();
                    foreach ($students as $student) {
                        $attendanceRows[] = [
                            'student_id' => $student->id,
                            'status' => $defaultStatus ?? 'present',
                            'remarks' => null,
                        ];
                    }
                }
            } elseif (!empty($providedClass)) {
                $students = Student::where('class', $providedClass)->get();
                foreach ($students as $student) {
                    $attendanceRows[] = [
                        'student_id' => $student->id,
                        'status' => $defaultStatus ?? 'present',
                        'remarks' => null,
                    ];
                }
            }
        }

        $payload = [
            'date' => $date,
            'period' => $period,
            'class' => $providedClass,
            'class_id' => $providedClassId,
            'section_id' => $providedSectionId,
            'attendance_rows' => $attendanceRows,
        ];

        $result = $preflightService->preflight($payload);

        return view('attendance.preflight-result', compact('result'));
    }
}
