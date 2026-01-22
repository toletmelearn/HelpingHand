<?php

namespace App\Http\Controllers\API;

use App\Models\Attendance;
use App\Models\Student;
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
            return $this->success($attendances, 'Attendance records retrieved successfully');
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
            $validated = $request->validate([
                'student_id' => 'required|exists:students,id',
                'date' => 'required|date',
                'status' => 'required|in:present,absent,late,half_day',
                'remarks' => 'nullable|string|max:255',
                'period' => 'nullable|string|max:50',
                'subject' => 'nullable|string|max:100',
                'class' => 'required|string|max:50',
                'session' => 'nullable|string|max:20',
                'marked_by' => 'required|exists:users,id'
            ]);

            // Check if attendance is already marked
            if (Attendance::where('student_id', $validated['student_id'])
                          ->where('date', $validated['date'])
                          ->where('period', $validated['period'] ?? null)
                          ->exists()) {
                return $this->error('Attendance already marked for this student on this date and period.', 409);
            }

            $attendance = Attendance::create($validated);
            return $this->success($attendance, 'Attendance marked successfully', 201);
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
            return $this->success($attendance, 'Attendance record retrieved successfully');
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
                'student_id' => 'sometimes|required|exists:students,id',
                'date' => 'sometimes|required|date',
                'status' => 'sometimes|required|in:present,absent,late,half_day',
                'remarks' => 'nullable|string|max:255',
                'period' => 'nullable|string|max:50',
                'subject' => 'nullable|string|max:100',
                'class' => 'sometimes|required|string|max:50',
                'session' => 'nullable|string|max:20',
                'marked_by' => 'sometimes|required|exists:users,id'
            ]);

            $attendance->update($validated);
            return $this->success($attendance, 'Attendance record updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update attendance: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
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
            return $this->success($attendances, 'Daily attendance report retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve daily report: ' . $e->getMessage());
        }
    }

    /**
     * Bulk mark attendance for multiple students.
     */
    public function bulkMark(Request $request): JsonResponse
    {
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
}