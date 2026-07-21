<?php

namespace App\Http\Controllers\API;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\Attendance;
use App\Models\StudentFeeLedger;
use App\Models\Exam;
use App\Models\Result;
use App\Models\LessonPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Support\Attendance\AttendanceCreditCalculator;

class DashboardController extends BaseApiController
{
    /**
     * Student Dashboard
     */
    public function studentDashboard(Request $request)
    {
        $user = $request->user();
        $student = Student::with(['schoolClass', 'schoolSection'])->where('user_id', $user->id)->first();

        if (!$student) {
            return $this->sendError('Student not found', ['error' => 'No student record associated with this account'], 404);
        }

        // Get attendance stats
        $attendanceStats = $this->getStudentAttendanceStats($student->id);
        
        // Get fee status
        $feeStatus = $this->getStudentFeeStatus($student->id);
        
        // Get upcoming exams
        $upcomingExams = $this->getUpcomingExams($student->school_class_id, $student->section_id);
        
        // Get recent results
        $recentResults = $this->getRecentResults($student->id);
        
        // Get today's lesson plans
        $todayLessonPlans = $this->getTodayLessonPlans($student->school_class_id, $student->section_id);
        
        // Get unread notifications
        $unreadNotifications = $user->unreadNotifications()->count();

        return $this->sendResponse([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'roll_number' => $student->roll_number,
                'class' => $student->schoolClass?->name ?? $student->class ?? 'N/A',
                'section' => $student->schoolSection?->name ?? $student->section ?? 'N/A',
            ],
            'attendance' => $attendanceStats,
            'fees' => $feeStatus,
            'upcoming_exams' => $upcomingExams,
            'recent_results' => $recentResults,
            'today_lessons' => $todayLessonPlans,
            'unread_notifications' => $unreadNotifications,
        ], 'Student dashboard data retrieved successfully');
    }

    /**
     * Parent Dashboard
     */
    public function parentDashboard(Request $request)
    {
        $user = $request->user();
        
        // Get children (students linked to this parent)
        $children = Student::with(['schoolClass', 'schoolSection'])->whereHas('guardians', function ($query) use ($user) {
            $query->where('guardian_id', $user->id);
        })->get();

        if ($children->isEmpty()) {
            return $this->sendError('No children found', ['error' => 'No student records associated with this parent account'], 404);
        }

        $childrenData = [];
        foreach ($children as $child) {
            $childrenData[] = [
                'id' => $child->id,
                'name' => $child->name,
                'roll_number' => $child->roll_number,
                'class' => $child->schoolClass?->name ?? $child->class ?? 'N/A',
                'section' => $child->schoolSection?->name ?? $child->section ?? 'N/A',
                'attendance' => $this->getStudentAttendanceStats($child->id),
                'fees' => $this->getStudentFeeStatus($child->id),
                'recent_results' => $this->getRecentResults($child->id, 3),
            ];
        }

        return $this->sendResponse([
            'children' => $childrenData,
            'unread_notifications' => $user->unreadNotifications()->count(),
        ], 'Parent dashboard data retrieved successfully');
    }

    /**
     * Teacher Dashboard
     */
    public function teacherDashboard(Request $request)
    {
        $user = $request->user();
        $teacher = Teacher::where('user_id', $user->id)->first();

        if (!$teacher) {
            return $this->sendError('Teacher not found', ['error' => 'No teacher record associated with this account'], 404);
        }

        // Get assigned classes
        $assignedClasses = $teacher->classSubjectAssignments()
            ->with(['schoolClass', 'section', 'subject'])
            ->get();

        // Get today's attendance marking status
        $todayAttendance = Attendance::where('teacher_id', $teacher->id)
            ->whereDate('date', Carbon::today())
            ->count();

        // Get pending lesson plans
        $pendingLessonPlans = $this->getPendingLessonPlansCount($teacher->id);

        return $this->sendResponse([
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'designation' => $teacher->designation,
                'wing' => $teacher->wing,
            ],
            'assigned_classes' => $assignedClasses,
            'today_attendance_marked' => $todayAttendance,
            'pending_lesson_plans' => $pendingLessonPlans,
            'unread_notifications' => $user->unreadNotifications()->count(),
        ], 'Teacher dashboard data retrieved successfully');
    }

    // Helper methods
    private function getStudentAttendanceStats($studentId)
    {
        $thisMonth = Carbon::now()->month;
        $thisYear = Carbon::now()->year;

        $records = Attendance::where('student_id', $studentId)
            ->whereMonth('date', $thisMonth)
            ->whereYear('date', $thisYear)
            ->get(['status']);

        $summary = AttendanceCreditCalculator::summarizeRecords($records, 'status');

        return [
            'total_days' => $summary['total_days'],
            'present_days' => $summary['present_days'],
            'absent_days' => $summary['absent_days'],
            'percentage' => $summary['attendance_rate'],
            'month' => Carbon::now()->format('F Y'),
            // Aligned fields (additive)
            'attendance_rate' => $summary['attendance_rate'],
            'attendance_credit' => $summary['attendance_credit'],
            'late_days' => $summary['late_days'],
            'half_days' => $summary['half_days'],
            'leave_days' => $summary['leave_days'],
        ];
    }

    private function getStudentFeeStatus($studentId)
    {
        $totalFees = (float) StudentFeeLedger::where('student_id', $studentId)->sum('debit');
        $paidFees = (float) StudentFeeLedger::where('student_id', $studentId)->sum('credit');
        $pendingFees = round($totalFees - $paidFees, 2);

        return [
            'total' => $totalFees,
            'paid' => $paidFees,
            'pending' => $pendingFees,
            'status' => $pendingFees > 0 ? 'pending' : 'paid',
        ];
    }

    private function getUpcomingExams($classId, $sectionId, $limit = 5)
    {
        return Exam::where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->where('date', '>=', Carbon::today())
            ->orderBy('date')
            ->limit($limit)
            ->get(['id', 'name', 'date', 'subject_id', 'total_marks']);
    }

    private function getRecentResults($studentId, $limit = 5)
    {
        return Result::where('student_id', $studentId)
            ->with(['exam:id,name,date', 'subject:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get(['id', 'exam_id', 'subject_id', 'marks_obtained', 'total_marks', 'grade']);
    }

    private function getTodayLessonPlans($classId, $sectionId)
    {
        $query = LessonPlan::where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->whereDate('date', Carbon::today());

        if (Schema::hasColumn('lesson_plans', 'status')) {
            $query->where('status', 'published');
        }

        return $query->with(['teacher:id,name', 'subject:id,name'])
            ->get(['id', 'teacher_id', 'subject_id', 'topic', 'date']);
    }

    private function getPendingLessonPlansCount($teacherId)
    {
        $query = LessonPlan::where('teacher_id', $teacherId);

        if (Schema::hasColumn('lesson_plans', 'status')) {
            $query->where('status', 'draft');
        }

        return $query->count();
    }
}
