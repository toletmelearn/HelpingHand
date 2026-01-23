<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Attendance;
use App\Models\Result;

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
     * Generate fee collection summary report PDF
     */
    public function generateFeeCollectionReport()
    {
        $fees = \App\Models\Fee::with('student')->get();
        
        $pdf = Pdf::loadView('reports.fee-collection', compact('fees'));
        return $pdf->download('fee-collection-report.pdf');
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
        $totalDays = $attendances->count();
        $presentDays = $attendances->where('status', 'present')->count();
        $absentDays = $attendances->where('status', 'absent')->count();
        $lateDays = $attendances->where('status', 'late')->count();
        
        $attendancePercentage = $totalDays > 0 ? ($presentDays / $totalDays) * 100 : 0;
        
        return [
            'total_days' => $totalDays,
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'late_days' => $lateDays,
            'attendance_percentage' => round($attendancePercentage, 2)
        ];
    }
}