<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Attendance;
use App\Models\Result;
use App\Support\Attendance\AttendanceCreditCalculator;

class PDFReportService
{
    /**
     * Generate student report PDF
     */
    public function generateStudentReport($studentId)
    {
        $student = Student::with(['attendances', 'results', 'fees'])->findOrFail($studentId);
        
        $pdf = Pdf::loadView('reports.student', compact('student'));
        return $pdf->download('student-report-' . $student->name . '.pdf');
    }

    /**
     * Generate class strength report PDF
     */
    public function generateClassStrengthReport()
    {
        $students = Student::all()->groupBy('class');
        
        $pdf = Pdf::loadView('reports.class-strength', compact('students'));
        return $pdf->download('class-strength-report.pdf');
    }

    /**
     * Generate student category distribution report PDF
     */
    public function generateCategoryDistributionReport()
    {
        $categories = ['General', 'OBC', 'SC', 'ST'];
        $categoryWise = [];
        
        foreach ($categories as $category) {
            $categoryWise[$category] = Student::where('category', $category)->get();
        }
        
        $pdf = Pdf::loadView('reports.category-distribution', compact('categoryWise'));
        return $pdf->download('category-distribution-report.pdf');
    }

    /**
     * Generate individual attendance report PDF
     */
    public function generateAttendanceReport($studentId)
    {
        $student = Student::with('attendances')->findOrFail($studentId);
        $attendanceStats = $this->calculateAttendanceStats($student->attendances);
        
        $pdf = Pdf::loadView('reports.attendance', compact('student', 'attendanceStats'));
        return $pdf->download('attendance-report-' . $student->name . '.pdf');
    }

    /**
     * Generate class attendance summary report PDF
     */
    public function generateClassAttendanceReport($class)
    {
        $students = Student::where('class', $class)->with('attendances')->get();
        $attendanceSummaries = [];
        
        foreach ($students as $student) {
            $attendanceSummaries[$student->id] = $this->calculateAttendanceStats($student->attendances);
        }
        
        $pdf = Pdf::loadView('reports.class-attendance', compact('students', 'attendanceSummaries', 'class'));
        return $pdf->download('class-attendance-report-' . $class . '.pdf');
    }

    /**
     * Generate teacher report PDF
     */
    public function generateTeacherReport($teacherId)
    {
        $teacher = Teacher::with(['attendances', 'classes', 'examPapers'])->findOrFail($teacherId);
        
        $pdf = Pdf::loadView('reports.teacher', compact('teacher'));
        return $pdf->download('teacher-report-' . $teacher->name . '.pdf');
    }

    /**
     * Calculate attendance statistics
     */
    private function calculateAttendanceStats($attendances)
    {
        $calcSummary = AttendanceCreditCalculator::summarizeRecords($attendances, 'status');
        
        return [
            'total_days' => $calcSummary['total_days'],
            'present_days' => $calcSummary['present_days'],
            'absent_days' => $calcSummary['absent_days'],
            'late_days' => $calcSummary['late_days'],
            'attendance_percentage' => $calcSummary['attendance_rate'],
            'attendance_rate' => $calcSummary['attendance_rate'],
            'attendance_credit' => $calcSummary['attendance_credit'],
            'half_days' => $calcSummary['half_days'],
            'leave_days' => $calcSummary['leave_days']
        ];
    }
}