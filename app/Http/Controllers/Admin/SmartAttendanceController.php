<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\LowAttendanceAlert;
use App\Support\Attendance\AttendanceCreditCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class SmartAttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display attendance dashboard with smart analytics
     */
    public function index(Request $request)
    {
        $date = $request->date ?? now()->toDateString();
        $class = $request->class;
        
        // Get attendance statistics
        $stats = $this->getAttendanceStatistics($date, $class);
        
        // Get attendance trends
        $trends = $this->getAttendanceTrends($class);
        
        // Get attendance warnings (students with low attendance)
        $attendanceWarnings = $this->getAttendanceWarnings();
        
        // Get classes for filter
        $classes = Student::distinct()->pluck('class')->filter()->sort()->values();
        
        return view('admin.attendance.smart-dashboard', compact(
            'stats', 
            'trends', 
            'attendanceWarnings', 
            'classes', 
            'date', 
            'class'
        ));
    }

    private function getAttendanceStatistics($date, $class = null)
    {
        $query = Attendance::whereDate('date', $date);
        
        if ($class) {
            $query->where('class', $class);
        }
        
        $records = $query->get(['status']);
        $summary = AttendanceCreditCalculator::summarizeRecords($records, 'status');
        
        return [
            'total' => $summary['total_days'],
            'present' => $summary['present_days'],
            'absent' => $summary['absent_days'],
            'late' => $summary['late_days'],
            'half_day' => $summary['half_days'],
            'leave' => $summary['leave_days'],
            'attendance_rate' => $summary['attendance_rate'],
            'attendance_credit' => $summary['attendance_credit']
        ];
    }

    /**
     * Get attendance trends
     */
    private function getAttendanceTrends($class = null)
    {
        $query = Attendance::whereBetween('date', [now()->subDays(30), now()]);
        
        if ($class) {
            $query->where('class', $class);
        }
        
        $records = $query->get(['date', 'status']);
        
        $grouped = $records->groupBy(function($item) {
            return Carbon::parse($item->date)->toDateString();
        })->sortKeys();
        
        $dates = [];
        $rates = [];
        $counts = [];
        
        foreach ($grouped as $dateStr => $dayRecords) {
            $summary = AttendanceCreditCalculator::summarizeRecords($dayRecords, 'status');
            $dates[] = $dateStr;
            $rates[] = $summary['attendance_rate'];
            $counts[] = $summary['total_days'];
        }
        
        return [
            'dates' => collect($dates),
            'attendance_rates' => collect($rates),
            'attendance_counts' => collect($counts)
        ];
    }

    /**
     * Get attendance warnings (students with low attendance)
     */
    private function getAttendanceWarnings()
    {
        $students = Student::with(['attendances' => function($query) {
            $query->whereBetween('date', [now()->subDays(30), now()]);
        }])->get();
        
        $warnings = [];
        
        foreach ($students as $student) {
            $records = $student->attendances;
            $totalDays = $records->count();
            
            if ($totalDays > 0) {
                $summary = AttendanceCreditCalculator::summarizeRecords($records, 'status');
                $attendancePercentage = $summary['attendance_rate'];
                
                if ($attendancePercentage < 75) { // Below 75% attendance threshold
                    $warnings[] = [
                        'student' => $student,
                        'percentage' => $attendancePercentage,
                        'total_days' => $summary['total_days'],
                        'present_days' => $summary['present_days'],
                        'absent_days' => $summary['absent_days'],
                        'late_days' => $summary['late_days'],
                        'half_days' => $summary['half_days'],
                        'attendance_credit' => $summary['attendance_credit']
                    ];
                }
            }
        }
        
        return collect($warnings)->sortByDesc('percentage')->take(10); // Top 10 warnings
    }

    public function sendAttendanceAlerts(Request $request)
    {
        // Temporary guard: prevent sending attendance alerts until reporting policy is aligned.
        return redirect()->back()->with('warning', 'Attendance alert sending is temporarily disabled until attendance reporting policy is aligned.');

        $request->validate([
            'threshold' => 'required|numeric|min:0|max:100',
            'class' => 'nullable|string'
        ]);
        
        $threshold = $request->threshold;
        $class = $request->class;
        
        // Get students with attendance below threshold
        $students = Student::with(['attendances' => function($query) {
            $query->whereBetween('date', [now()->subDays(30), now()]);
        }]);
        
        if ($class) {
            $students = $students->where('class', $class);
        }
        
        $students = $students->get();
        
        $alertCount = 0;
        $failedCount = 0;
        
        foreach ($students as $student) {
            $records = $student->attendances;
            $totalDays = $records->count();
            
            if ($totalDays > 0) {
                $summary = AttendanceCreditCalculator::summarizeRecords($records, 'status');
                $attendancePercentage = $summary['attendance_rate'];
                
                if ($attendancePercentage < $threshold) {
                    try {
                        // Send notification to student's guardian
                        if ($student->guardian) {
                            $student->guardian->notify(new LowAttendanceAlert($student, $attendancePercentage));
                        }
                        
                        // Also notify teachers responsible for the class
                        $teachers = Teacher::where('assigned_class', $student->class)->get();
                        foreach ($teachers as $teacher) {
                            if ($teacher->user) {
                                $teacher->user->notify(new LowAttendanceAlert($student, $attendancePercentage));
                            }
                        }
                        
                        $alertCount++;
                    } catch (\Exception $e) {
                        $failedCount++;
                    }
                }
            }
        }
        
        return redirect()->back()->with('success', 
            "Sent $alertCount attendance alerts. Failed: $failedCount"
        );
    }

    /**
     * Generate monthly attendance report for a student
     */
    public function monthlyReport($studentId, Request $request)
    {
        $student = Student::findOrFail($studentId);
        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;
        
        $report = Attendance::getStudentMonthlyReport($studentId, $month, $year);
        
        return view('admin.attendance.monthly-report', compact('report', 'student'));
    }

    /**
     * Generate bulk monthly reports for a class
     */
    public function bulkMonthlyReports(Request $request)
    {
        $request->validate([
            'class' => 'required|string',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2099'
        ]);
        
        $class = $request->class;
        $month = $request->month;
        $year = $request->year;
        
        $students = Student::where('class', $class)->get();
        $reports = [];
        
        foreach ($students as $student) {
            $report = Attendance::getStudentMonthlyReport($student->id, $month, $year);
            $reports[] = $report;
        }
        
        return view('admin.attendance.bulk-monthly-reports', compact('reports', 'class', 'month', 'year'));
    }

    /**
     * Auto-mark attendance based on biometric data (simulated)
     */
    public function autoMarkAttendance(Request $request)
    {
        $request->validate([
            'class' => 'required|string',
            'date' => 'required|date',
            'auto_mark_threshold' => 'required|integer|min:0|max:100' // Percentage for auto-marking
        ]);
        
        $class = $request->class;
        $date = $request->date;
        $threshold = $request->auto_mark_threshold;
        
        // Get students in the class
        $students = Student::where('class', $class)->get();
        
        $markedCount = 0;
        
        foreach ($students as $student) {
            // Check if attendance already exists
            $existingAttendance = Attendance::where('student_id', $student->id)
                ->whereDate('date', $date)
                ->first();
            
            if (!$existingAttendance) {
                // Simulate biometric check - if student's biometric data exists for this date/time
                // In a real system, this would check against biometric logs
                
                // For now, let's mark as present based on threshold probability
                $shouldMarkPresent = rand(1, 100) <= $threshold;
                
                Attendance::create([
                    'student_id' => $student->id,
                    'date' => $date,
                    'status' => $shouldMarkPresent ? 'present' : 'absent',
                    'remarks' => $shouldMarkPresent ? 'Auto-marked via biometric simulation' : 'Auto-marked absent',
                    'class' => $class,
                    'session' => date('Y') . '-' . (date('Y') + 1),
                    'marked_by' => auth()->id(),
                    'ip_address' => request()->ip(),
                    'device_info' => request()->userAgent(),
                ]);
                
                $markedCount++;
            }
        }
        
        return redirect()->back()->with('success', 
            "Auto-marked attendance for $markedCount students in class $class on $date"
        );
    }

    /**
     * Get attendance analytics dashboard
     */
    public function analytics()
    {
        // Get overall attendance statistics
        $overallStats = $this->getOverallAttendanceStats();
        
        // Get class-wise attendance
        $classWiseStats = $this->getClassWiseAttendance();
        
        // Get attendance trends
        $trends = $this->getMonthlyAttendanceTrends();
        
        return view('admin.attendance.analytics', compact(
            'overallStats',
            'classWiseStats',
            'trends'
        ));
    }

    private function getOverallAttendanceStats()
    {
        $totalStudents = Student::count();
        $records = Attendance::whereBetween('date', [now()->subMonth(), now()])
            ->get(['status']);
        
        $summary = AttendanceCreditCalculator::summarizeRecords($records, 'status');
        
        return [
            'total_students' => $totalStudents,
            'attendance_rate' => $summary['attendance_rate'],
            'present_count' => $summary['present_days'],
            'total_records' => $summary['total_days'],
            'attendance_credit' => $summary['attendance_credit']
        ];
    }

    /**
     * Get class-wise attendance statistics
     */
    private function getClassWiseAttendance()
    {
        $classes = Student::distinct()->pluck('class')->filter()->sort()->values();
        $stats = [];
        
        foreach ($classes as $class) {
            $classStudents = Student::where('class', $class)->pluck('id')->toArray();
            $records = Attendance::whereIn('student_id', $classStudents)
                ->whereBetween('date', [now()->subMonth(), now()])
                ->get(['status']);
            
            $summary = AttendanceCreditCalculator::summarizeRecords($records, 'status');
            
            $stats[] = [
                'class' => $class,
                'attendance_rate' => $summary['attendance_rate'],
                'present_count' => $summary['present_days'],
                'total_records' => $summary['total_days'],
                'attendance_credit' => $summary['attendance_credit']
            ];
        }
        
        return collect($stats)->sortByDesc('attendance_rate');
    }

    /**
     * Get monthly attendance trends
     */
    private function getMonthlyAttendanceTrends()
    {
        $trends = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $startDate = (clone $month)->startOfMonth();
            $endDate = (clone $month)->endOfMonth();
            
            $records = Attendance::whereBetween('date', [$startDate, $endDate])
                ->get(['status']);
            
            $summary = AttendanceCreditCalculator::summarizeRecords($records, 'status');
            
            $trends[] = [
                'month' => $month->format('M Y'),
                'attendance_rate' => $summary['attendance_rate'],
                'present_count' => $summary['present_days'],
                'total_count' => $summary['total_days'],
                'attendance_credit' => $summary['attendance_credit']
            ];
        }
        
        return $trends;
    }
}