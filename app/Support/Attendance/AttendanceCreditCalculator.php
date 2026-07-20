<?php

namespace App\Support\Attendance;

class AttendanceCreditCalculator
{
    public static function creditForStatus(?string $status): float
    {
        if (!$status) {
            return 0.0;
        }

        switch ($status) {
            case 'present':
            case 'late':
                return 1.0;
            case 'half_day':
                return 0.5;
            case 'absent':
            case 'leave':
            default:
                return 0.0;
        }
    }

    public static function summarize(iterable $statuses): array
    {
        $total = 0;
        $present = 0;
        $absent = 0;
        $leave = 0;
        $late = 0;
        $half = 0;
        $credit = 0.0;

        foreach ($statuses as $s) {
            $total++;

            $status = null;
            if (is_array($s)) {
                $status = $s['status'] ?? null;
            } elseif (is_object($s)) {
                $status = $s->status ?? null;
            } else {
                $status = $s;
            }

            switch ($status) {
                case 'present':
                    $present++;
                    $credit += 1.0;
                    break;
                case 'late':
                    $late++;
                    $credit += 1.0;
                    break;
                case 'half_day':
                    $half++;
                    $credit += 0.5;
                    break;
                case 'absent':
                    $absent++;
                    break;
                case 'leave':
                    $leave++;
                    break;
                default:
                    // unknown status counts in total but gives 0 credit
                    break;
            }
        }

        $rate = $total > 0 ? round(($credit / $total) * 100, 2) : 0;

        return [
            'total_days' => $total,
            'present_days' => $present,
            'absent_days' => $absent,
            'leave_days' => $leave,
            'late_days' => $late,
            'half_days' => $half,
            'attendance_credit' => $credit,
            'attendance_rate' => $rate,
        ];
    }

    public static function summarizeRecords(iterable $records, string $statusKey = 'status'): array
    {
        $statuses = [];
        foreach ($records as $r) {
            if (is_array($r)) {
                $statuses[] = $r[$statusKey] ?? null;
            } elseif (is_object($r)) {
                $statuses[] = $r->{$statusKey} ?? null;
            } else {
                $statuses[] = $r;
            }
        }

        return self::summarize($statuses);
    }
}
