<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AdvancedReportExport implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $studentStats = $this->data['studentStats'];
        $feeStats = $this->data['feeStats'];
        $attendanceStats = $this->data['attendanceStats'];
        $examStats = $this->data['examStats'];
        $libraryStats = $this->data['libraryStats'];
        $biometricStats = $this->data['biometricStats'];

        return collect([
            // Student Statistics
            ['STUDENT STATISTICS', '', '', ''],
            ['Total Students', $studentStats['total_students'], '', ''],
            ['New Admissions', $studentStats['new_admissions'], '', ''],
            ['Active Students', $studentStats['active_students'], '', ''],
            ['Passed Out', $studentStats['passed_out'], '', ''],
            ['Left School', $studentStats['left_school'], '', ''],
            ['', '', '', ''],
            
            // Fee Statistics
            ['FEE STATISTICS', '', '', ''],
            ['Total Fees Collected', '₹' . number_format($feeStats['total_fees_collected']), '', ''],
            ['Pending Dues', '₹' . number_format($feeStats['pending_dues']), '', ''],
            ['Overdue Fees', '₹' . number_format($feeStats['overdue_fees']), '', ''],
            ['Payments This Period', $feeStats['payments_this_period'], '', ''],
            ['', '', '', ''],
            
            // Attendance Statistics
            ['ATTENDANCE STATISTICS', '', '', ''],
            ['Attendance Rate', $attendanceStats['attendance_rate'] . '%', '', ''],
            ['Total Attendance Records', $attendanceStats['total_attendance'], '', ''],
            ['Present Count', $attendanceStats['present_count'], '', ''],
            ['Absent Count', $attendanceStats['absent_count'], '', ''],
            ['Late Arrivals', $attendanceStats['late_arrivals'], '', ''],
            ['', '', '', ''],
            
            // Exam Statistics
            ['EXAM STATISTICS', '', '', ''],
            ['Total Exams', $examStats['total_exams'], '', ''],
            ['Upcoming Exams', $examStats['upcoming_exams'], '', ''],
            ['Completed Exams', $examStats['completed_exams'], '', ''],
            ['Results Published', $examStats['results_published'], '', ''],
            ['', '', '', ''],
            
            // Library Statistics
            ['LIBRARY STATISTICS', '', '', ''],
            ['Total Books', $libraryStats['total_books'], '', ''],
            ['Available Books', $libraryStats['available_books'], '', ''],
            ['Issued Books', $libraryStats['issued_books'], '', ''],
            ['Books Issued This Period', $libraryStats['books_issued_this_period'], '', ''],
            ['Overdue Books', $libraryStats['overdue_books'], '', ''],
            ['', '', '', ''],
            
            // Biometric Statistics
            ['BIOMETRIC STATISTICS', '', '', ''],
            ['Teacher Attendance Rate', $biometricStats['attendance_rate'] . '%', '', ''],
            ['Total Records', $biometricStats['total_teacher_records'], '', ''],
            ['On Time Arrivals', $biometricStats['on_time_arrivals'], '', ''],
            ['Late Arrivals', $biometricStats['late_arrivals'], '', ''],
            ['Early Departures', $biometricStats['early_departures'], '', ''],
        ]);
    }

    public function headings(): array
    {
        return [
            'Report Generated: ' . now()->format('Y-m-d H:i:s'),
            '',
            '',
            ''
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['bold' => true, 'size' => 12]],
            9 => ['font' => ['bold' => true, 'size' => 12]],
            16 => ['font' => ['bold' => true, 'size' => 12]],
            23 => ['font' => ['bold' => true, 'size' => 12]],
            29 => ['font' => ['bold' => true, 'size' => 12]],
            36 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }

    public function title(): string
    {
        return 'Advanced Report';
    }
}
