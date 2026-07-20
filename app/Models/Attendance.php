<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\Attendance\AttendanceCreditCalculator;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'teacher_id',
        'date',
        'status', // present, absent, late, half_day
        'remarks',
        'period', // for period-wise attendance
        'subject', // subject name
        'class', // class/section
        'session', // academic session
        'marked_by', // teacher/user who marked attendance
        'ip_address', // for tracking
        'device_info' // device information
    ];

    protected $casts = [
        'date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationship with Student
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    // Relationship with Teacher
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    // Relationship with User who marked attendance
    public function markedBy()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    // Scopes for common queries
    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', 'absent');
    }

    public function scopeLate($query)
    {
        return $query->where('status', 'late');
    }

    public function scopeByDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeByClass($query, $class)
    {
        return $query->where('class', $class);
    }

    public function scopeBySubject($query, $subject)
    {
        return $query->where('subject', $subject);
    }

    // Helper methods
    public static function getAttendanceStats($date = null, $class = null)
    {
        // Build queries with same filters for each metric to avoid scope accumulation
        $total = self::when($date, function ($q) use ($date) { $q->whereDate('date', $date); })
            ->when($class, function ($q) use ($class) { $q->where('class', $class); })
            ->count();

        $present = self::when($date, function ($q) use ($date) { $q->whereDate('date', $date); })
            ->when($class, function ($q) use ($class) { $q->where('class', $class); })
            ->where('status', 'present')
            ->count();

        $absent = self::when($date, function ($q) use ($date) { $q->whereDate('date', $date); })
            ->when($class, function ($q) use ($class) { $q->where('class', $class); })
            ->where('status', 'absent')
            ->count();

        $late = self::when($date, function ($q) use ($date) { $q->whereDate('date', $date); })
            ->when($class, function ($q) use ($class) { $q->where('class', $class); })
            ->where('status', 'late')
            ->count();

        $halfDay = self::when($date, function ($q) use ($date) { $q->whereDate('date', $date); })
            ->when($class, function ($q) use ($class) { $q->where('class', $class); })
            ->where('status', 'half_day')
            ->count();

        $leave = self::when($date, function ($q) use ($date) { $q->whereDate('date', $date); })
            ->when($class, function ($q) use ($class) { $q->where('class', $class); })
            ->where('status', 'leave')
            ->count();

        // Retrieve status records for calculator summarization
        $records = self::when($date, function ($q) use ($date) { $q->whereDate('date', $date); })
            ->when($class, function ($q) use ($class) { $q->where('class', $class); })
            ->get(['status']);

        $summary = AttendanceCreditCalculator::summarizeRecords($records, 'status');

        // Map calculator keys while preserving legacy keys
        return [
            'total' => $summary['total_days'],
            'present' => $summary['present_days'],
            'absent' => $summary['absent_days'],
            'late' => $summary['late_days'],
            'half_day' => $summary['half_days'],
            'leave' => $summary['leave_days'],
            'attendance_credit' => $summary['attendance_credit'],
            'attendance_rate' => $summary['attendance_rate'],
            'percentage' => $summary['attendance_rate']
        ];
    }

    // Get monthly attendance report for a student
    public static function getStudentMonthlyReport($studentId, $month, $year)
    {
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        $attendances = self::where('student_id', $studentId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        $report = [];
        foreach ($attendances as $attendance) {
            $report[] = [
                'date' => $attendance->date->format('Y-m-d'),
                'status' => $attendance->status,
                'remarks' => $attendance->remarks
            ];
        }

        // Use calculator to summarize statuses (preserves credit policy)
        $summary = AttendanceCreditCalculator::summarizeRecords($attendances, 'status');

        return [
            'student_id' => $studentId,
            'month' => $month,
            'year' => $year,
            'details' => $report,
            'summary' => [
                'total_days' => $summary['total_days'],
                'present' => $summary['present_days'],
                'absent' => $summary['absent_days'],
                'late' => $summary['late_days'],
                'half_day' => $summary['half_days'],
                'leave' => $summary['leave_days'],
                'attendance_credit' => $summary['attendance_credit'],
                'attendance_rate' => $summary['attendance_rate'],
                'percentage' => $summary['attendance_rate']
            ]
        ];
    }

    // Check if attendance is already marked for a class on a date
    public static function isMarked($class, $date, $period = null)
    {
        // When period is provided, check for that specific period
        if ($period) {
            return self::where('class', $class)
                ->where('date', $date)
                ->where('period', $period)
                ->exists();
        } else {
            // When period is null, check if any attendance exists for the day
            // This prevents marking full-day attendance multiple times
            return self::where('class', $class)
                ->where('date', $date)
                ->exists();
        }
    }

    // Get today's attendance status for all students in a class
    public static function getTodayAttendance($class, $date = null)
    {
        $date = $date ?: now()->toDateString();

        return self::where('class', $class)
            ->where('date', $date)
            ->with('student')
            ->get();
    }
}