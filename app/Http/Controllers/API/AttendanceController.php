<?php

namespace App\Http\Controllers\API;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\StudentStatus;
use App\Services\Attendance\AttendanceClassResolver;
use App\Support\Attendance\AttendancePeriodPresenter;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AttendanceController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $attendances = Attendance::with(['student', 'teacher', 'markedBy'])->get();
            return $this->success($this->transformAttendanceCollectionForApi($attendances), 'Attendance records retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve attendance records: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return $this->error('Authenticated API user is required to mark attendance.', 401);
            }

            $validated = $request->validate([
                'student_id' => 'required|exists:students,id',
                'date' => 'required|date',
                'status' => 'required|in:present,absent,late,half_day',
                'remarks' => 'nullable|string|max:255',
                'period' => 'nullable|string|max:50',
                'subject' => 'nullable|string|max:100',
                'class' => 'nullable|string|max:50',
                'session' => 'nullable|string|max:20'
            ]);

            // Phase 5L: marked_by is derived from authenticated API user and cannot be supplied by client.
            $validated['marked_by'] = $user->id;

            $student = Student::find($validated['student_id']);

            if (!$student) {
                return $this->error('Student is not eligible for attendance marking.', 422);
            }

            // Phase 5R: reject terminal/inactive students using the latest student_statuses id.
            $latestStatus = StudentStatus::where('student_id', $student->id)
                ->orderByDesc('id')
                ->value('status');

            if (in_array($latestStatus, ['passed_out', 'left_school', 'tc_issued', 'inactive'], true)) {
                return $this->error('Attendance cannot be marked for terminal or inactive student.', 409);
            }

            // Phase 6N/6P: derive attendance class from student; client class is not trusted.
            $classResolution = app(AttendanceClassResolver::class)->resolveForStudent($student);

            if (!$classResolution['ok']) {
                return $this->error($classResolution['message'], $classResolution['status']);
            }

            $validated['class'] = $classResolution['class'];

            // Check if attendance is already marked
            if (Attendance::where('student_id', $validated['student_id'])
                          ->where('date', $validated['date'])
                          ->where('period', $validated['period'] ?? null)
                          ->exists()) {
                return $this->error('Attendance already marked for this student on this date and period.', 409);
            }

            $attendance = Attendance::create($validated)->refresh();
            return $this->success($this->transformAttendanceForApi($attendance), 'Attendance marked successfully', 201);
        } catch (QueryException $e) {
            if ($this->isDuplicateAttendanceException($e)) {
                return $this->error('Attendance already marked for this student on this date and period.', 409);
            }

            return $this->error('Failed to mark attendance: ' . $e->getMessage());
        } catch (\Exception $e) {
            return $this->error('Failed to mark attendance: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $attendance = Attendance::with(['student', 'teacher', 'markedBy'])->findOrFail($id);
            return $this->success($this->transformAttendanceForApi($attendance), 'Attendance record retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Attendance record not found: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $attendance = Attendance::findOrFail($id);

            $validated = $request->validate([
                'status' => 'sometimes|required|in:present,absent,late,half_day',
                'remarks' => 'nullable|string|max:255',
                'subject' => 'nullable|string|max:100',
                'session' => 'nullable|string|max:20'
            ]);

            // Phase 5P: update cannot mutate attendance identity/date/period fields; corrections need a dedicated workflow.
            unset($validated['student_id'], $validated['class'], $validated['date'], $validated['period']);
            // Phase 5L: marked_by is derived from authenticated API user and cannot be supplied by client.
            unset($validated['marked_by']);

            $attendance->update($validated);
            return $this->success($this->transformAttendanceForApi($attendance->refresh()), 'Attendance record updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update attendance: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        // Phase 5M: hard delete disabled until audit-preserving attendance correction workflow exists.
        return $this->error(
            'API attendance deletion is temporarily disabled. Use an audit-preserving correction workflow once enabled.',
            423
        );

        try {
            $attendance = Attendance::findOrFail($id);
            $attendance->delete();
            return $this->success(null, 'Attendance record deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete attendance: ' . $e->getMessage());
        }
    }

    /**
     * Get student's monthly attendance report.
     */
    public function studentMonthlyReport(int $studentId, int $month, int $year): JsonResponse
    {
        try {
            $student = Student::findOrFail($studentId);
            $report = Attendance::getStudentMonthlyReport($studentId, $month, $year);
            return $this->success($report, 'Monthly attendance report retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve monthly report: ' . $e->getMessage());
        }
    }

    /**
     * Get daily attendance report for a class.
     */
    public function dailyReport(string $classSection, string $date): JsonResponse
    {
        try {
            $attendances = Attendance::where('class', $classSection)
                                    ->where('date', $date)
                                    ->with('student')
                                    ->get();
            return $this->success($this->transformAttendanceCollectionForApi($attendances), 'Daily attendance report retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve daily report: ' . $e->getMessage());
        }
    }

    /**
     * Bulk mark attendance for multiple students.
     */
    public function bulkMark(Request $request): JsonResponse
    {
        return $this->error(
            'API bulk attendance marking is temporarily disabled. Use attendance preflight first. Safe API bulk apply is not enabled yet.',
            423
        );

        try {
            $request->validate([
                'date' => 'required|date',
                'class' => 'required|string|max:50',
                'subject' => 'required|string|max:100',
                'period' => 'nullable|string|max:50',
                'student_ids' => 'required|array',
                'student_ids.*' => 'exists:students,id',
                'statuses' => 'required|array',
                'statuses.*' => 'in:present,absent,late,half_day',
                'marked_by' => 'required|exists:users,id'
            ]);

            $attendances = [];
            $timestamp = now();

            foreach ($request->student_ids as $index => $studentId) {
                $attendances[] = [
                    'student_id' => $studentId,
                    'date' => $request->date,
                    'status' => $request->statuses[$index] ?? 'absent',
                    'remarks' => $request->remarks[$index] ?? null,
                    'period' => $request->period,
                    'subject' => $request->subject,
                    'class' => $request->class,
                    'session' => date('Y') . '-' . (date('Y') + 1),
                    'marked_by' => $request->marked_by,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp
                ];
            }

            Attendance::insert($attendances);

            return $this->success(null, 'Attendance marked successfully for ' . count($attendances) . ' students!', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to bulk mark attendance: ' . $e->getMessage());
        }
    }

    private function transformAttendanceForApi(Attendance $attendance): array
    {
        $data = $attendance->toArray();
        $data['period_display'] = AttendancePeriodPresenter::display($attendance->period);

        return $data;
    }

    private function transformAttendanceCollectionForApi($attendances): array
    {
        return $attendances
            ->map(fn (Attendance $attendance) => $this->transformAttendanceForApi($attendance))
            ->values()
            ->all();
    }

    private function isDuplicateAttendanceException(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        $driverCode = (string) ($exception->errorInfo[1] ?? '');
        $message = strtolower($exception->getMessage());
        $isDuplicateSignal = $sqlState === '23000'
            || $driverCode === '1062'
            || str_contains($message, 'unique constraint failed')
            || str_contains($message, 'duplicate entry');

        if (!$isDuplicateSignal) {
            return false;
        }

        return str_contains($message, 'attendances_student_id_date_period_unique')
            || str_contains($message, 'attendances.student_id')
            || str_contains($message, 'attendances.date')
            || str_contains($message, 'student_id_date_period');
    }
}
