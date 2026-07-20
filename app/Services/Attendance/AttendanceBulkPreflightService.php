<?php

namespace App\Services\Attendance;

use App\Models\Student;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AttendanceBulkPreflightService
{
    protected $allowedStatuses = ['present','absent','late','half_day'];
    protected AttendanceClassResolver $classResolver;

    public function __construct(?AttendanceClassResolver $classResolver = null)
    {
        $this->classResolver = $classResolver ?? app(AttendanceClassResolver::class);
    }

    public function preflight(array $payload): array
    {
        $rows = Arr::get($payload, 'attendance_rows', []);
        $date = Arr::get($payload, 'date');
        $period = Arr::get($payload, 'period');
        $providedClassId = Arr::get($payload, 'class_id');
        $providedClass = Arr::get($payload, 'class');
        $providedSectionId = Arr::get($payload, 'section_id');

        $summary = [
            'total_rows' => count($rows),
            'valid_rows' => 0,
            'rows_with_errors' => 0,
            'rows_with_warnings' => 0,
            'would_create' => 0,
            'would_update' => 0,
            'would_skip' => 0,
        ];

        $normalized = [];
        $errors = [];
        $warnings = [];

        $seen = [];

        foreach ($rows as $index => $r) {
            $rowNum = $index + 1;
            $rowErrors = [];
            $rowWarnings = [];
            $student = null;

            $studentId = Arr::get($r, 'student_id');
            $requestedStatus = Str::lower(Arr::get($r, 'status', ''));

            if (empty($date)) {
                $rowErrors[] = 'missing_date';
            } else {
                // validate date format YYYY-MM-DD
                $d = date_create_from_format('Y-m-d', $date);
                if (!$d || $d->format('Y-m-d') !== $date) {
                    $rowErrors[] = 'invalid_date_format';
                }
            }

            if (empty($studentId)) {
                $rowErrors[] = 'missing_student_id';
            } else {
                $student = Student::find($studentId);
                if (!$student) {
                    $rowErrors[] = 'invalid_student_id';
                }
            }

            if ($requestedStatus === '') {
                $rowErrors[] = 'missing_status';
            } elseif (!in_array($requestedStatus, $this->allowedStatuses, true)) {
                $rowErrors[] = 'invalid_status';
            }

            // duplicate detection inside payload by student_id+date+period
            $dupKey = $studentId . '|' . $date . '|' . $period;
            if (isset($seen[$dupKey])) {
                $rowErrors[] = 'duplicate_in_payload';
            }
            $seen[$dupKey] = true;

            $normalizedRow = [
                'row' => $rowNum,
                'student_id' => $studentId,
                'student_name' => $student->name ?? null,
                'date' => $date,
                'period' => $period,
                'requested_status' => $requestedStatus,
                'normalized_status' => $requestedStatus,
                'class_id' => $student->class_id ?? null,
                'school_class_id' => $student->school_class_id ?? null,
                'legacy_class' => $student->class ?? null,
                'derived_class' => null,
                'derived_class_source' => null,
                'class_resolution_ok' => null,
                'class_resolution_status' => null,
                'class_resolution_message' => null,
                'section_id' => $student->section_id ?? null,
                'legacy_section' => $student->section ?? null,
                'existing_attendance_id' => null,
                'action' => 'create',
                'error' => null,
            ];

            if (!empty($student)) {
                $classResolution = $this->classResolver->resolveForStudent($student);
                $normalizedRow['derived_class'] = $classResolution['class'];
                $normalizedRow['derived_class_source'] = $classResolution['source'];
                $normalizedRow['class_resolution_ok'] = $classResolution['ok'];
                $normalizedRow['class_resolution_status'] = $classResolution['status'];
                $normalizedRow['class_resolution_message'] = $classResolution['message'];

                if (!$classResolution['ok']) {
                    $rowErrors[] = $classResolution['source'] === 'conflict'
                        ? 'student_class_conflict'
                        : 'student_class_unresolved';
                }

                // latest status
                $latestStatus = DB::table('student_statuses')
                    ->where('student_id', $studentId)
                    ->orderBy('id', 'desc')
                    ->limit(1)
                    ->value('status');

                if ($latestStatus) {
                    $normalizedRow['latest_status'] = $latestStatus;
                } else {
                    $normalizedRow['latest_status'] = 'active';
                }

                // soft-deleted
                if (!empty($student->deleted_at)) {
                    $rowWarnings[] = 'student_soft_deleted';
                }

                // terminal statuses
                if (in_array($normalizedRow['latest_status'], ['passed_out','left_school','tc_issued','inactive'], true)) {
                    $rowWarnings[] = 'terminal_status_' . $normalizedRow['latest_status'];
                    $normalizedRow['action'] = 'skip';
                }

                // class mismatches
                if (!empty($providedClassId) && $providedClassId != $student->class_id) {
                    $rowWarnings[] = 'payload_class_id_mismatch';
                }
                if (!empty($providedClass) && $providedClass != $student->class) {
                    $rowWarnings[] = 'payload_legacy_class_mismatch';
                }
                if (!empty($providedClass) && $classResolution['class'] !== null && $providedClass != $classResolution['class']) {
                    $rowWarnings[] = 'payload_derived_class_mismatch';
                }
                if (!empty($providedSectionId) && $providedSectionId != $student->section_id) {
                    $rowWarnings[] = 'payload_section_id_mismatch';
                }

                // existing attendance
                $existing = DB::table('attendances')
                    ->where('student_id', $studentId)
                    ->where('date', $date)
                    ->where(function ($q) use ($period) {
                        if ($period === null) {
                            $q->whereNull('period');
                        } else {
                            $q->where('period', $period);
                        }
                    })
                    ->first();

                if ($existing) {
                    $normalizedRow['existing_attendance_id'] = $existing->id;
                    // risk: legacy class mismatch
                    if (isset($existing->class) && $existing->class != $student->class) {
                        $rowWarnings[] = 'existing_attendance_legacy_class_mismatch';
                    }
                    // recommend update
                    if ($normalizedRow['action'] !== 'skip') {
                        $normalizedRow['action'] = 'update';
                    }
                }
            }

            if (!empty($rowErrors)) {
                $summary['rows_with_errors']++;
                $normalizedRow['error'] = $rowErrors;
                $errors[$rowNum] = $rowErrors;
                $normalizedRow['action'] = 'error';
            } else {
                $summary['valid_rows']++;
                if ($normalizedRow['action'] === 'create') {
                    $summary['would_create']++;
                } elseif ($normalizedRow['action'] === 'update') {
                    $summary['would_update']++;
                } elseif ($normalizedRow['action'] === 'skip') {
                    $summary['would_skip']++;
                }
            }

            if (!empty($rowWarnings)) {
                $summary['rows_with_warnings']++;
                $warnings[$rowNum] = $rowWarnings;
            }

            $normalized[] = $normalizedRow;
        }

        $isValid = $summary['rows_with_errors'] === 0;

        return [
            'summary' => $summary,
            'normalized' => $normalized,
            'errors' => $errors,
            'warnings' => $warnings,
            'is_valid' => $isValid,
        ];
    }
}
