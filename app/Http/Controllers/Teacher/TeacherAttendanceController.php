<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use App\Services\AttendanceNotificationService;
use App\Models\Student;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TeacherAttendanceController extends Controller
{
    protected $attendanceService;
    protected $notificationService;

    public function __construct(
        AttendanceService $attendanceService,
        AttendanceNotificationService $notificationService
    ) {
        $this->attendanceService = $attendanceService;
        $this->notificationService = $notificationService;
    }

    /**
     * Display teacher attendance dashboard
     */
    public function dashboard()
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? $teacherLogin->teacher : null;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }
        
        $today = Carbon::today();
        
        // Get teacher's class attendance data
        $classData = $this->attendanceService->getTeacherClassAttendance($teacher->id, $today);
        
        // Get today's attendance summary
        $todaySummary = $this->attendanceService->getClassAttendanceSummary();
        
        // Get low attendance alerts
        $lowAttendanceAlerts = $this->attendanceService->getLowAttendanceAlerts(75, 30);
        
        return view('teacher.attendance.dashboard', compact(
            'classData', 
            'todaySummary', 
            'lowAttendanceAlerts'
        ));
    }

    /**
     * Show attendance marking interface for a specific class
     */
    public function markAttendance($classId)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? $teacherLogin->teacher : null;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }
        
        // Verify teacher has access to this class
        $this->authorize('markAttendance', [null, $classId]);
        
        $schoolClass = SchoolClass::findOrFail($classId);
        $students = Student::where('class_id', $classId)->get();
        $today = Carbon::today();
        
        // Get existing attendance records for today
        $existingAttendance = $this->attendanceService->getClassAttendanceSummary($classId, $today);
        
        return view('teacher.attendance.mark', compact(
            'schoolClass', 
            'students', 
            'today', 
            'existingAttendance'
        ));
    }

    /**
     * Process attendance marking
     */
    public function storeAttendance(Request $request)
    {
        // Phase 6Y: disable teacher attendance writes until class/status/schema policy is aligned.
        return redirect()->back()->with(
            'warning',
            'Teacher attendance marking is temporarily disabled until class/status/schema policy is aligned.'
        );

        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'date' => 'required|date',
            'attendance' => 'required|array',
            'attendance.*.student_id' => 'required|exists:students,id',
            'attendance.*.status' => 'required|in:present,absent,leave'
        ]);

        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? $teacherLogin->teacher : null;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }
        
        $attendanceData = [];
        
        foreach ($request->attendance as $record) {
            $attendanceData[] = [
                'student_id' => $record['student_id'],
                'date' => $request->date,
                'status' => $record['status'],
                'remarks' => $record['remarks'] ?? null,
                'class_id' => $request->class_id
            ];
        }
        
        // Mark attendance
        $markedRecords = $this->attendanceService->markAttendance($attendanceData, $teacher->id);
        
        // Send notifications
        $this->notificationService->sendBulkAttendanceNotifications($attendanceData);
        
        return redirect()->route('teacher.attendance.dashboard')
            ->with('success', 'Attendance marked successfully for ' . count($markedRecords) . ' students.');
    }

    /**
     * Show attendance reports
     */
    public function reports(Request $request)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? $teacherLogin->teacher : null;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }
        
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
        
        // Get teacher's assigned classes
        $classData = $this->attendanceService->getTeacherClassAttendance($teacher->id);
        $classes = collect($classData)->pluck('class')->unique('id');
        
        $selectedClassId = $request->get('class_id');
        
        $report = null;
        if ($selectedClassId) {
            $report = $this->attendanceService->generateAttendanceReport(
                $selectedClassId, 
                $startDate, 
                $endDate
            );
        }
        
        return view('teacher.attendance.reports', compact(
            'classes', 
            'selectedClassId', 
            'startDate', 
            'endDate', 
            'report'
        ));
    }

    /**
     * Show student attendance details
     */
    public function studentAttendance($studentId)
    {
        $student = Student::findOrFail($studentId);
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? $teacherLogin->teacher : null;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }
        
        // Verify teacher has access to this student's class
        // FIXED: Use assignment-based access, not direct teacher->class_id
        $hasAccess = \App\Models\TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
            ->where('class_id', $student->class_id)
            ->exists();
        
        if (!$hasAccess) {
            abort(403, 'Unauthorized access to student attendance.');
        }
        
        $startDate = Carbon::now()->subMonths(3);
        $endDate = Carbon::now();
        
        $attendanceStats = $this->attendanceService->getStudentAttendanceStats($studentId, $startDate, $endDate);
        $attendanceTrends = $this->attendanceService->getAttendanceTrends($studentId, 6);
        
        return view('teacher.attendance.student', compact(
            'student', 
            'attendanceStats', 
            'attendanceTrends'
        ));
    }

    /**
     * Edit existing attendance record
     */
    public function editAttendance($attendanceId)
    {
        $attendance = \App\Models\Attendance::findOrFail($attendanceId);
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? $teacherLogin->teacher : null;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }
        
        // Verify teacher has permission to edit
        $this->authorize('update', $attendance);
        
        $student = $attendance->student;
        
        return view('teacher.attendance.edit', compact('attendance', 'student'));
    }

    /**
     * Update attendance record
     */
    public function updateAttendance(Request $request, $attendanceId)
    {
        // Phase 7A: disable teacher attendance updates until class/status/schema policy is aligned.
        return redirect()->back()->with(
            'warning',
            'Teacher attendance updates are temporarily disabled until class/status/schema policy is aligned.'
        );

        $request->validate([
            'status' => 'required|in:present,absent,leave',
            'remarks' => 'nullable|string'
        ]);
        
        $attendance = \App\Models\Attendance::findOrFail($attendanceId);
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? $teacherLogin->teacher : null;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }
        
        // Verify teacher has permission to update
        $this->authorize('update', $attendance);
        
        $attendance->update([
            'status' => $request->status,
            'remarks' => $request->remarks,
            'updated_by' => $teacher->id
        ]);
        
        return redirect()->route('teacher.attendance.dashboard')
            ->with('success', 'Attendance record updated successfully.');
    }

    /**
     * Export attendance data
     */
    public function exportAttendance(Request $request)
    {
        $this->authorize('export', \App\Models\Attendance::class);
        
        $classId = $request->get('class_id');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        
        // Generate and download export
        // Implementation would depend on export format (CSV, Excel, PDF)
        
        return response()->json(['message' => 'Export functionality to be implemented']);
    }
}
