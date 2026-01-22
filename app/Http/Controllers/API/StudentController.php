<?php

namespace App\Http\Controllers\API;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StudentController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $students = Student::with(['user', 'attendances', 'fees', 'results'])->get();
            return $this->success($students, 'Students retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve students: ' . $e->getMessage());
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
                'father_name' => 'required|string|max:255',
                'mother_name' => 'required|string|max:255',
                'date_of_birth' => 'required|date|before:today',
                'aadhar_number' => 'required|digits:12|unique:students',
                'address' => 'required|string',
                'phone' => 'required|digits:10',
                'gender' => 'required|in:male,female,other',
                'category' => 'required|in:General,OBC,SC,ST,Other',
                'class' => 'required|string|max:50',
                'section' => 'nullable|string|max:10',
                'roll_number' => 'nullable|integer|unique:students',
                'religion' => 'nullable|string|max:50',
                'caste' => 'nullable|string|max:50',
                'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-,unknown'
            ]);

            $student = Student::create($validated);
            return $this->success($student, 'Student created successfully', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to create student: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $student = Student::with(['user', 'attendances', 'fees', 'results'])->findOrFail($id);
            return $this->success($student, 'Student retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Student not found: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $student = Student::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'father_name' => 'required|string|max:255',
                'mother_name' => 'required|string|max:255',
                'date_of_birth' => 'required|date|before:today',
                'aadhar_number' => 'required|digits:12|unique:students,aadhar_number,' . $id,
                'address' => 'required|string',
                'phone' => 'required|digits:10',
                'gender' => 'required|in:male,female,other',
                'category' => 'required|in:General,OBC,SC,ST,Other',
                'class' => 'required|string|max:50',
                'section' => 'nullable|string|max:10',
                'roll_number' => 'nullable|integer|unique:students,roll_number,' . $id,
                'religion' => 'nullable|string|max:50',
                'caste' => 'nullable|string|max:50',
                'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-,unknown'
            ]);

            $student->update($validated);
            return $this->success($student, 'Student updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update student: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $student = Student::findOrFail($id);
            $student->delete();
            return $this->success(null, 'Student deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete student: ' . $e->getMessage());
        }
    }

    /**
     * Get student's attendance records.
     */
    public function attendance(int $id): JsonResponse
    {
        try {
            $student = Student::with('attendances')->findOrFail($id);
            return $this->success($student->attendances, 'Attendance records retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve attendance: ' . $e->getMessage());
        }
    }

    /**
     * Get student's results.
     */
    public function results(int $id): JsonResponse
    {
        try {
            $student = Student::with('results')->findOrFail($id);
            return $this->success($student->results, 'Results retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve results: ' . $e->getMessage());
        }
    }

    /**
     * Get student's fees.
     */
    public function fees(int $id): JsonResponse
    {
        try {
            $student = Student::with('fees')->findOrFail($id);
            return $this->success($student->fees, 'Fee records retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve fees: ' . $e->getMessage());
        }
    }
}