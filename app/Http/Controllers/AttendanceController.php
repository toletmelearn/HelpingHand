<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Display a listing of attendance records.
     */
    public function index(Request $request)
    {
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
        
        return view('attendance.index', compact('attendances', 'classes'));
    }

    /**
     * Show the form for marking daily attendance.
     */
    public function create(Request $request)
    {
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
        
        // Get students in the selected class
        $students = Student::where('class', $class)
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
        // Handle bulk marking if classes are provided
        if ($request->filled('classes') && $request->filled('default_status')) {
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
        
        $attendances = [];
        $timestamp = now();
        
        foreach ($request->student_ids as $index => $studentId) {
            $attendances[] = [
                'student_id' => $studentId,
                'date' => $request->date,
                'status' => $request->statuses[$index] ?? 'absent',
                'remarks' => $request->remarks[$index] ?? null,
                'period' => $request->period,
                'subject' => $request->subject,
                'class' => $request->class,
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
        $attendance->load(['student', 'teacher', 'markedBy']);
        return view('attendance.show', compact('attendance'));
    }

    /**
     * Show attendance reports.
     */
    public function reports(Request $request)
    {
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
        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;
        
        $report = Attendance::getStudentMonthlyReport($studentId, $month, $year);
        $student = Student::findOrFail($studentId);
        
        return view('attendance.student_report', compact('report', 'student'));
    }

    /**
     * Bulk attendance marking for multiple classes.
     */
    public function bulkMark(Request $request)
    {
        $classes = Student::distinct()->pluck('class')->filter()->sortBy('class');
        return view('attendance.bulk_mark', compact('classes'));
    }

    /**
     * Export attendance data.
     */
    public function export(Request $request)
    {
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
                'Subject', 'Period', 'Remarks', 'Marked By', 'IP Address'
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
        $attendance->delete();
        
        return redirect()->route('attendance.index')
            ->with('success', 'Attendance record deleted successfully!');
    }
}