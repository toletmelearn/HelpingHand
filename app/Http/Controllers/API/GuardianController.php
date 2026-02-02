<?php

namespace App\Http\Controllers\API;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GuardianController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $guardians = Guardian::with(['students.user', 'students.attendances', 'students.results', 'students.fees'])->get();
            return $this->success($guardians, 'Guardians retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve guardians: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'relationship' => 'required|string|max:100',
                'phone' => 'required|digits:10|unique:guardians',
                'email' => 'required|email|unique:guardians',
                'occupation' => 'nullable|string|max:255',
                'address' => 'nullable|string',
                'aadhar_number' => 'nullable|digits:12|unique:guardians',
                'is_primary' => 'boolean',
                'is_active' => 'boolean',
                'student_ids' => 'array|nullable',
                'student_ids.*' => 'exists:students,id'
            ]);

            $guardian = Guardian::create(array_merge(
                $validated,
                ['is_primary' => $validated['is_primary'] ?? false, 'is_active' => $validated['is_active'] ?? true]
            ));

            if (isset($validated['student_ids'])) {
                $guardian->students()->sync($validated['student_ids']);
            }

            return $this->success($guardian, 'Guardian created successfully', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to create guardian: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $guardian = Guardian::with(['students.user', 'students.attendances', 'students.results', 'students.fees'])->findOrFail($id);
            return $this->success($guardian, 'Guardian retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Guardian not found: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $guardian = Guardian::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'relationship' => 'sometimes|required|string|max:100',
                'phone' => 'sometimes|required|digits:10|unique:guardians,phone,' . $id,
                'email' => 'sometimes|required|email|unique:guardians,email,' . $id,
                'occupation' => 'nullable|string|max:255',
                'address' => 'nullable|string',
                'aadhar_number' => 'nullable|digits:12|unique:guardians,aadhar_number,' . $id,
                'is_primary' => 'boolean',
                'is_active' => 'boolean',
                'student_ids' => 'array|nullable',
                'student_ids.*' => 'exists:students,id'
            ]);

            $guardian->update($validated);

            if (isset($validated['student_ids'])) {
                $guardian->students()->sync($validated['student_ids']);
            }

            return $this->success($guardian, 'Guardian updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update guardian: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $guardian = Guardian::findOrFail($id);
            $guardian->delete();
            return $this->success(null, 'Guardian deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete guardian: ' . $e->getMessage());
        }
    }

    /**
     * Get guardian's children with their progress.
     */
    public function children(int $id): JsonResponse
    {
        try {
            $guardian = Guardian::with([
                'students.user', 
                'students.attendances', 
                'students.results', 
                'students.fees',
                'students.guardians'
            ])->findOrFail($id);

            $childrenData = [];
            foreach ($guardian->students as $student) {
                $childrenData[] = [
                    'id' => $student->id,
                    'name' => $student->name,
                    'user' => $student->user,
                    'class' => $student->class,
                    'section' => $student->section,
                    'roll_number' => $student->roll_number,
                    'attendance_percentage' => $this->calculateAttendancePercentage($student->attendances),
                    'latest_results' => $student->results->take(5), // Latest 5 results
                    'outstanding_fees' => $this->calculateOutstandingFees($student->fees),
                    'total_fees' => $this->calculateTotalFees($student->fees),
                    'paid_fees' => $this->calculatePaidFees($student->fees),
                ];
            }

            return $this->success($childrenData, 'Children progress retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve children progress: ' . $e->getMessage());
        }
    }

    /**
     * Get guardian's notifications.
     */
    public function notifications(int $id): JsonResponse
    {
        try {
            // In the current system, notifications are linked to users
            // Guardians might be linked to users through the student relationship
            $guardian = Guardian::with(['students.user'])->findOrFail($id);
            
            $allNotifications = collect();
            foreach ($guardian->students as $student) {
                if ($student->user) {
                    $notifications = $student->user->notifications;
                    $allNotifications = $allNotifications->merge($notifications);
                }
            }

            // Sort by created_at descending
            $sortedNotifications = $allNotifications->sortByDesc('created_at')->values();

            return $this->success($sortedNotifications, 'Notifications retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve notifications: ' . $e->getMessage());
        }
    }

    /**
     * Calculate attendance percentage for a student.
     */
    private function calculateAttendancePercentage($attendances)
    {
        if ($attendances->count() === 0) {
            return 0;
        }

        $presentCount = $attendances->filter(function ($attendance) {
            return $attendance->status === 'Present';
        })->count();

        return round(($presentCount / $attendances->count()) * 100, 2);
    }

    /**
     * Calculate outstanding fees for a student.
     */
    private function calculateOutstandingFees($fees)
    {
        $totalAmount = $fees->sum('amount');
        $paidAmount = $fees->filter(function ($fee) {
            return $fee->payment_status === 'Paid';
        })->sum('amount');

        return $totalAmount - $paidAmount;
    }

    /**
     * Calculate total fees for a student.
     */
    private function calculateTotalFees($fees)
    {
        return $fees->sum('amount');
    }

    /**
     * Calculate paid fees for a student.
     */
    private function calculatePaidFees($fees)
    {
        return $fees->filter(function ($fee) {
            return $fee->payment_status === 'Paid';
        })->sum('amount');
    }
}