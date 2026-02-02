<?php

namespace App\Http\Controllers\API;

use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TeacherController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $teachers = Teacher::with(['user', 'classes', 'examPapers'])->get();
            return $this->success($teachers, 'Teachers retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve teachers: ' . $e->getMessage());
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
                'email' => 'required|email|unique:teachers,email',
                'phone' => 'required|digits:10',
                'address' => 'required|string',
                'date_of_birth' => 'required|date|before:today',
                'gender' => 'required|in:male,female,other',
                'qualification' => 'required|string|max:100',
                'experience_years' => 'required|integer|min:0',
                'subject_specialization' => 'required|string|max:100',
                'designation' => 'required|string|max:100',
                'salary' => 'required|numeric|min:0',
                'joining_date' => 'required|date',
                'status' => 'required|in:active,inactive,resigned',
                'department' => 'nullable|string|max:100',
                'employee_id' => 'nullable|string|max:50|unique:teachers,employee_id',
                'emergency_contact' => 'nullable|digits:10',
                'emergency_contact_person' => 'nullable|string|max:255',
                'password' => 'required|string|min:6|confirmed'
            ]);

            $teacher = Teacher::create($validated);
            return $this->success($teacher, 'Teacher created successfully', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to create teacher: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $teacher = Teacher::with(['user', 'classes', 'examPapers'])->findOrFail($id);
            return $this->success($teacher, 'Teacher retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Teacher not found: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $teacher = Teacher::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:teachers,email,' . $id,
                'phone' => 'required|digits:10',
                'address' => 'required|string',
                'date_of_birth' => 'required|date|before:today',
                'gender' => 'required|in:male,female,other',
                'qualification' => 'required|string|max:100',
                'experience_years' => 'required|integer|min:0',
                'subject_specialization' => 'required|string|max:100',
                'designation' => 'required|string|max:100',
                'salary' => 'required|numeric|min:0',
                'joining_date' => 'required|date',
                'status' => 'required|in:active,inactive,resigned',
                'department' => 'nullable|string|max:100',
                'employee_id' => 'nullable|string|max:50|unique:teachers,employee_id,' . $id,
                'emergency_contact' => 'nullable|digits:10',
                'emergency_contact_person' => 'nullable|string|max:255',
                'password' => 'nullable|string|min:6|confirmed'
            ]);

            $teacher->update($validated);
            return $this->success($teacher, 'Teacher updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update teacher: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $teacher = Teacher::findOrFail($id);
            $teacher->delete();
            return $this->success(null, 'Teacher deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete teacher: ' . $e->getMessage());
        }
    }

    /**
     * Get teacher's assigned classes.
     */
    public function classes(int $id): JsonResponse
    {
        try {
            $teacher = Teacher::with('classes')->findOrFail($id);
            return $this->success($teacher->classes, 'Classes retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve classes: ' . $e->getMessage());
        }
    }

    /**
     * Get teacher's exam papers.
     */
    public function examPapers(int $id): JsonResponse
    {
        try {
            $teacher = Teacher::with('examPapers')->findOrFail($id);
            return $this->success($teacher->examPapers, 'Exam papers retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve exam papers: ' . $e->getMessage());
        }
    }
}