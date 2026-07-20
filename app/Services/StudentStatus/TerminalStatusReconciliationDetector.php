<?php

namespace App\Services\StudentStatus;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TerminalStatusReconciliationDetector
{
    private const TERMINAL_STATUSES = ['passed_out', 'left_school', 'tc_issued'];

    public function detect(): array
    {
        $terminalStatusDrift = $this->latestTerminalStatusDrift();
        $passedOutWithoutLog = $this->passedOutStatusesWithoutLogs();
        $passedOutLogsWithoutLatestStatus = $this->passedOutLogsWithoutLatestPassedOut();
        $suspiciousPassedOutLogs = $this->suspiciousPassedOutLogs();
        $classFkConflicts = $this->classIdConflicts();
        $classFkNullMismatches = $this->classIdNullMismatches();

        return [
            'terminal_status_drift' => $terminalStatusDrift,
            'passed_out_without_log' => $passedOutWithoutLog,
            'passed_out_logs_without_latest_status' => $passedOutLogsWithoutLatestStatus,
            'suspicious_passed_out_logs' => $suspiciousPassedOutLogs,
            'class_fk_conflicts' => $classFkConflicts,
            'class_fk_null_mismatches' => $classFkNullMismatches,
            'summary' => [
                'terminal_status_drift' => $terminalStatusDrift->count(),
                'passed_out_without_log' => $passedOutWithoutLog->count(),
                'passed_out_logs_without_latest_status' => $passedOutLogsWithoutLatestStatus->count(),
                'suspicious_passed_out_logs' => $suspiciousPassedOutLogs->count(),
                'class_fk_conflicts' => $classFkConflicts->count(),
                'class_fk_null_mismatches' => $classFkNullMismatches->count(),
            ],
        ];
    }

    private function latestTerminalStatusDrift(): Collection
    {
        return $this->latestStatuses()
            ->join('students as s', 's.id', '=', 'ss.student_id')
            ->whereIn('ss.status', self::TERMINAL_STATUSES)
            ->where(function ($query) {
                $query->whereNotNull('s.class_id')
                    ->orWhereNotNull('s.school_class_id')
                    ->orWhereNotNull('s.section_id')
                    ->orWhereNotNull('s.section')
                    ->orWhere(function ($passedOutQuery) {
                        $passedOutQuery->where('ss.status', 'passed_out')
                            ->where(function ($classQuery) {
                                $classQuery->whereNull('s.class')
                                    ->orWhere('s.class', '!=', 'Passed Out');
                            });
                    });
            })
            ->select(
                's.id as student_id',
                's.name',
                'ss.status as latest_status',
                's.class_id',
                's.school_class_id',
                's.class',
                's.section_id',
                's.section',
                DB::raw($this->passedOutLogExistsSql() . ' as matching_log_status')
            )
            ->orderBy('s.id')
            ->get();
    }

    private function passedOutStatusesWithoutLogs(): Collection
    {
        return $this->latestStatuses()
            ->join('students as s', 's.id', '=', 'ss.student_id')
            ->where('ss.status', 'passed_out')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('student_promotion_logs as spl')
                    ->whereColumn('spl.student_id', 'ss.student_id')
                    ->where('spl.to_class', 'Passed Out');
            })
            ->select(
                's.id as student_id',
                's.name',
                'ss.status as latest_status',
                's.class_id',
                's.school_class_id',
                's.class',
                's.section_id',
                's.section',
                DB::raw("'missing' as matching_log_status")
            )
            ->orderBy('s.id')
            ->get();
    }

    private function passedOutLogsWithoutLatestPassedOut(): Collection
    {
        return DB::table('student_promotion_logs as spl')
            ->leftJoin('students as s', 's.id', '=', 'spl.student_id')
            ->where('spl.to_class', 'Passed Out')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('student_statuses as ss')
                    ->joinSub($this->latestStatusIds(), 'latest', function ($join) {
                        $join->on('latest.id', '=', 'ss.id');
                    })
                    ->whereColumn('ss.student_id', 'spl.student_id')
                    ->where('ss.status', 'passed_out');
            })
            ->select(
                's.id as student_id',
                's.name',
                DB::raw("'missing_passed_out' as latest_status"),
                's.class_id',
                's.school_class_id',
                's.class',
                's.section_id',
                's.section',
                DB::raw("'present' as matching_log_status")
            )
            ->orderBy('spl.student_id')
            ->get();
    }

    private function suspiciousPassedOutLogs(): Collection
    {
        return DB::table('student_promotion_logs as spl')
            ->leftJoin('students as s', 's.id', '=', 'spl.student_id')
            ->where('spl.to_class', 'Passed Out')
            ->where(function ($query) {
                $query->whereNull('spl.from_class')
                    ->orWhereRaw("TRIM(spl.from_class) = ''")
                    ->orWhereIn('spl.from_class', ['Unknown', 'Passed Out']);
            })
            ->select(
                'spl.id as log_id',
                'spl.student_id',
                's.name',
                'spl.from_class',
                'spl.to_class'
            )
            ->orderBy('spl.id')
            ->get();
    }

    private function classIdConflicts(): Collection
    {
        return DB::table('students as s')
            ->whereNotNull('s.class_id')
            ->whereNotNull('s.school_class_id')
            ->whereColumn('s.class_id', '!=', 's.school_class_id')
            ->select(
                's.id as student_id',
                's.name',
                DB::raw("'class_fk_conflict' as latest_status"),
                's.class_id',
                's.school_class_id',
                's.class',
                's.section_id',
                's.section',
                DB::raw("'not_applicable' as matching_log_status")
            )
            ->orderBy('s.id')
            ->get();
    }

    private function classIdNullMismatches(): Collection
    {
        return DB::table('students as s')
            ->where(function ($query) {
                $query->where(function ($leftNull) {
                    $leftNull->whereNull('s.class_id')
                        ->whereNotNull('s.school_class_id');
                })->orWhere(function ($rightNull) {
                    $rightNull->whereNotNull('s.class_id')
                        ->whereNull('s.school_class_id');
                });
            })
            ->select(
                's.id as student_id',
                's.name',
                DB::raw("'class_fk_null_mismatch' as latest_status"),
                's.class_id',
                's.school_class_id',
                's.class',
                's.section_id',
                's.section',
                DB::raw("'not_applicable' as matching_log_status")
            )
            ->orderBy('s.id')
            ->get();
    }

    private function latestStatuses()
    {
        return DB::table('student_statuses as ss')
            ->joinSub($this->latestStatusIds(), 'latest', function ($join) {
                $join->on('latest.id', '=', 'ss.id');
            });
    }

    private function latestStatusIds()
    {
        return DB::table('student_statuses')
            ->select('student_id', DB::raw('MAX(id) as id'))
            ->groupBy('student_id');
    }

    private function passedOutLogExistsSql(): string
    {
        return "case when exists (
            select 1 from student_promotion_logs spl
            where spl.student_id = ss.student_id
            and spl.to_class = 'Passed Out'
        ) then 'present' else 'missing' end";
    }
}
