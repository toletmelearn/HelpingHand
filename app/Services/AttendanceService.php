<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\TeacherClassSubjectAssignment;
use App\Support\Attendance\AttendanceCreditCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    /**
     * Get student attendance statistics for a given period
     */
    public function getStudentAttendanceStats($studentId, $startDate = null, $endDate = null)
    {
        $startDate = $startDate ?: Carbon::now()->startOfMonth();
        $endDate = $endDate ?: Carbon::now()->endOfMonth();
        
        $attendanceRecords = Attendance::where('student_id', $studentId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();
            
        // Use shared pure calculator to summarize statuses (behavior-preserving)
        $summary = AttendanceCreditCalculator::summarizeRecords($attendanceRecords, 'status');

        return array_merge($summary, ['records' => $attendanceRecords]);
    }
    
    /**
     * Get class attendance statistics
     */
    public function getClassAttendanceStats($classId, $startDate = null, $endDate = null)
    {
        $startDate = $startDate ?: Carbon::now()->startOfMonth();
        $endDate = $endDate ?: Carbon::now()->endOfMonth();
        
        $students = Student::where('class_id', $classId)->get();
        $classStats = [];
        
        foreach ($students as $student) {
            $stats = $this->getStudentAttendanceStats($student->id, $startDate, $endDate);
            $classStats[] = [
                'student' => $student,
                'stats' => $stats
            ];
        }
        
        return $classStats;
    }
    
    /**
     * Get working days in a period
     */
    public function getWorkingDays($startDate = null, $endDate = null)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now()->endOfMonth();

        $workingDays = 0;
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            // Assuming Monday to Friday are working days
            if ($currentDate->isWeekday()) {
                $workingDays++;
            }
            $currentDate->addDay();
        }
        
        return $workingDays;
    }
    
    /**
     * Mark attendance for multiple students
     */
    public function markAttendance($attendanceData, $teacherId = null)
    {
        // Phase 7E: disabled until teacher attendance class/status/schema policy is aligned.
        throw new \RuntimeException(
            'AttendanceService::markAttendance is temporarily disabled until class/status/schema policy is aligned.'
        );

        $markedRecords = [];
        
        DB::beginTransaction();
        
        try {
            foreach ($attendanceData as $record) {
                $attendance = Attendance::updateOrCreate(
                    [
                        'student_id' => $record['student_id'],
                        'date' => $record['date']
                    ],
                    [
                        'status' => $record['status'],
                        'remarks' => $record['remarks'] ?? null,
                        'marked_by' => $teacherId,
                        'class_id' => $record['class_id'] ?? null
                    ]
                );
                
                $markedRecords[] = $attendance;
            }
            
            DB::commit();
            return $markedRecords;
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Attendance marking failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get attendance trends for a student
     */
    public function getAttendanceTrends($studentId, $months = 6)
    {
        $startDate = Carbon::now()->subMonths($months);
        $endDate = Carbon::now();
        
        $monthlyStats = [];
        
        for ($i = 0; $i < $months; $i++) {
            $monthStart = $startDate->copy()->addMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            
            $stats = $this->getStudentAttendanceStats($studentId, $monthStart, $monthEnd);
            
            $monthlyStats[] = [
                'month' => $monthStart->format('M Y'),
                'attendance_rate' => $stats['attendance_rate'],
                'present_days' => $stats['present_days'],
                'total_days' => $stats['total_days']
            ];
        }
        
        return $monthlyStats;
    }
    
    /**
     * Get class-wise attendance summary
     */
    public function getClassAttendanceSummary($classId = null, $date = null)
    {
        $date = $date ?: Carbon::today();
        
        $query = Attendance::whereDate('date', $date);
        
        if ($classId) {
            $query->whereHas('student', function($q) use ($classId) {
                $q->where('class_id', $classId);
            });
        }
        
        $attendance = $query->get();
        
        $calcSummary = AttendanceCreditCalculator::summarizeRecords($attendance, 'status');
        
        $summary = [
            'total_students' => $calcSummary['total_days'],
            'present' => $calcSummary['present_days'],
            'absent' => $calcSummary['absent_days'],
            'leave' => $calcSummary['leave_days'],
            'attendance_rate' => $calcSummary['attendance_rate'],
            'attendance_credit' => $calcSummary['attendance_credit'],
            'late_days' => $calcSummary['late_days'],
            'half_days' => $calcSummary['half_days'],
            'leave_days' => $calcSummary['leave_days']
        ];
        
        return $summary;
    }
    
    /**
     * Get low attendance alerts
     */
    public function getLowAttendanceAlerts($threshold = 75, $periodDays = 30)
    {
        $startDate = Carbon::now()->subDays($periodDays);
        $endDate = Carbon::now();
        
        $students = Student::with('attendances')->get();
        $alerts = [];
        
        foreach ($students as $student) {
            $stats = $this->getStudentAttendanceStats($student->id, $startDate, $endDate);
            
            if ($stats['attendance_rate'] < $threshold && $stats['total_days'] > 0) {
                $alerts[] = [
                    'student' => $student,
                    'attendance_rate' => $stats['attendance_rate'],
                    'present_days' => $stats['present_days'],
                    'total_days' => $stats['total_days'],
                    'absent_days' => $stats['absent_days']
                ];
            }
        }
        
        return $alerts;
    }
    
    /**
     * Get teacher's class attendance data
     */
    public function getTeacherClassAttendance($teacherId, $date = null)
    {
        $date = $date ?: Carbon::today();
        
        // Get teacher's assigned classes
        $assignments = TeacherClassSubjectAssignment::where('teacher_id', $teacherId)
            ->with(['schoolClass', 'subject'])
            ->get();
        
        $classData = [];
        
        foreach ($assignments as $assignment) {
            $classId = $assignment->schoolClass->id;
            
            // Get students in this class
            $students = Student::where('class_id', $classId)->get();
            
            // Get today's attendance for this class
            $attendanceRecords = Attendance::whereDate('date', $date)
                ->whereIn('student_id', $students->pluck('id'))
                ->get()
                ->keyBy('student_id');
            
            $classData[] = [
                'class' => $assignment->schoolClass,
                'subject' => $assignment->subject,
                'students' => $students->map(function($student) use ($attendanceRecords) {
                    $attendance = $attendanceRecords->get($student->id);
                    return [
                        'student' => $student,
                        'attendance' => $attendance,
                        'status' => $attendance ? $attendance->status : 'not_marked'
                    ];
                }),
                'summary' => $this->getClassAttendanceSummary($classId, $date)
            ];
        }
        
        return $classData;
    }
    
    /**
     * Generate attendance report
     */
    public function generateAttendanceReport($classId, $startDate, $endDate)
    {
        $students = Student::where('class_id', $classId)->get();
        
        $report = [
            'class' => SchoolClass::find($classId),
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'students' => [],
            'summary' => [
                'total_working_days' => $this->getWorkingDays($startDate, $endDate),
                'class_average' => 0
            ]
        ];
        
        $totalRates = 0;
        $studentCount = 0;
        
        foreach ($students as $student) {
            $stats = $this->getStudentAttendanceStats($student->id, $startDate, $endDate);
            
            $report['students'][] = [
                'student' => $student,
                'stats' => $stats
            ];
            
            if ($stats['total_days'] > 0) {
                $totalRates += $stats['attendance_rate'];
                $studentCount++;
            }
        }
        
        $report['summary']['class_average'] = $studentCount > 0 ? 
            round($totalRates / $studentCount, 2) : 0;
        
        return $report;
    }
}
