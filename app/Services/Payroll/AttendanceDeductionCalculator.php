<?php

namespace App\Services\Payroll;

use App\Models\AdminConfiguration;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherLeave;
use Carbon\Carbon;

class AttendanceDeductionCalculator
{
    /**
     * Compute how many days of pay a teacher's attendance/leave record costs them
     * for a given calendar month. Nothing here writes to the database -- callers
     * decide whether/how to apply the result, and the admin can always override
     * the resulting figure before a payslip is saved.
     *
     * Deduction rules (mirrors common Indian-school payroll convention):
     * - Every approved 'unpaid_leave' day within the period: 1 full day.
     * - Every unmarked absence (attendance status 'absent' with no covering
     *   approved leave of any type for that date): 1 full day.
     * - Every 'half_day' attendance mark: 0.5 day.
     * - Every N 'late' marks (N configurable, default 3): 0.5 day.
     */
    public function calculate(Teacher $teacher, int $month, int $year): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $leavesInPeriod = TeacherLeave::where('teacher_id', $teacher->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->get();

        $approvedLeaveDates = [];
        $unpaidLeaveDays = 0;

        foreach ($leavesInPeriod as $leave) {
            $clippedStart = $leave->start_date->lt($start) ? $start->copy() : $leave->start_date->copy();
            $clippedEnd = $leave->end_date->gt($end) ? $end->copy() : $leave->end_date->copy();

            if ($clippedStart->gt($clippedEnd)) {
                continue;
            }

            $daysInPeriod = $clippedStart->diffInDays($clippedEnd) + 1;

            for ($d = $clippedStart->copy(); $d->lte($clippedEnd); $d->addDay()) {
                $approvedLeaveDates[$d->format('Y-m-d')] = true;
            }

            if ($leave->leave_type === 'unpaid_leave') {
                $unpaidLeaveDays += $daysInPeriod;
            }
        }

        $attendanceInPeriod = TeacherAttendance::where('teacher_id', $teacher->id)
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get();

        $absentDays = $attendanceInPeriod
            ->where('status', 'absent')
            ->filter(fn ($a) => !isset($approvedLeaveDates[$a->date->format('Y-m-d')]))
            ->count();

        $halfDays = $attendanceInPeriod->where('status', 'half_day')->count();
        $lateCount = $attendanceInPeriod->where('status', 'late')->count();

        $lateThreshold = max(0, (int) AdminConfiguration::get('payroll', 'payroll_late_marks_per_deduction', 3));
        $lateDeductionDays = $lateThreshold > 0 ? floor($lateCount / $lateThreshold) * 0.5 : 0;

        $totalDeductionDays = $unpaidLeaveDays + $absentDays + ($halfDays * 0.5) + $lateDeductionDays;

        return [
            'unpaid_leave_days' => $unpaidLeaveDays,
            'absent_days' => $absentDays,
            'half_days' => $halfDays,
            'late_count' => $lateCount,
            'late_threshold' => $lateThreshold,
            'late_deduction_days' => $lateDeductionDays,
            'total_deduction_days' => round($totalDeductionDays, 2),
        ];
    }

    /**
     * Per-day pay rate used to convert deduction days into a rupee amount.
     * Fixed 30-day divisor, the standard convention for Indian payroll.
     */
    public function perDayRate(float $grossSalary): float
    {
        return round($grossSalary / 30, 2);
    }
}
