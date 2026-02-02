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

    /**
     * Get teacher's assigned subjects and classes.
     */
    public function subjectClasses(int $id): JsonResponse
    {
        try {
            $teacher = Teacher::with([
                'subjectAssignments.subject',
                'classAssignments.schoolClass',
                'lessonPlans'
            ])->findOrFail($id);
            
            $result = [
                'subjects' => $teacher->subjectAssignments->map(function($assignment) {
                    return [
                        'id' => $assignment->id,
                        'subject' => $assignment->subject,
                        'class' => $assignment->assigned_class,
                        'section' => $assignment->assigned_section,
                        'academic_session' => $assignment->academic_session,
                    ];
                }),
                'classes' => $teacher->classAssignments->map(function($assignment) {
                    return [
                        'id' => $assignment->id,
                        'class' => $assignment->assigned_class,
                        'section' => $assignment->assigned_section,
                        'academic_session' => $assignment->academic_session,
                        'is_class_teacher' => $assignment->is_class_teacher,
                        'students_count' => $assignment->schoolClass ? $assignment->schoolClass->students_count : 0,
                    ];
                }),
                'lesson_plans' => $teacher->lessonPlans,
            ];
            
            return $this->success($result, 'Subject and class assignments retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve subject and class assignments: ' . $e->getMessage());
        }
    }

    /**
     * Get teacher's attendance-related data.
     */
    public function attendanceData(int $id): JsonResponse
    {
        try {
            $teacher = Teacher::with([
                'classAssignments.schoolClass.students.attendances',
                'subjectAssignments.attendances'
            ])->findOrFail($id);
            
            $attendanceData = [
                'classes' => [],
                'attendance_records' => []
            ];
            
            foreach ($teacher->classAssignments as $classAssignment) {
                if ($classAssignment->schoolClass) {
                    $classAttendance = [
                        'class_id' => $classAssignment->schoolClass->id,
                        'class_name' => $classAssignment->schoolClass->class_name,
                        'section' => $classAssignment->schoolClass->section,
                        'total_students' => $classAssignment->schoolClass->students->count(),
                        'attendance_summary' => []
                    ];
                    
                    foreach ($classAssignment->schoolClass->students as $student) {
                        $studentAttendance = $student->attendances->groupBy('date')->map(function($dailyRecords) {
                            return [
                                'status' => $dailyRecords->first()->status ?? 'Absent',
                                'check_in' => $dailyRecords->first()->check_in_time,
                                'check_out' => $dailyRecords->first()->check_out_time,
                            ];
                        });
                        
                        $classAttendance['attendance_summary'][$student->id] = [
                            'student_name' => $student->name,
                            'attendance_records' => $studentAttendance
                        ];
                    }
                    
                    $attendanceData['classes'][] = $classAttendance;
                }
            }
            
            return $this->success($attendanceData, 'Attendance data retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve attendance data: ' . $e->getMessage());
        }
    }

    /**
     * Get teacher's grading and assessment data.
     */
    public function gradingData(int $id): JsonResponse
    {
        try {
            $teacher = Teacher::with([
                'subjectAssignments.examPapers',
                'subjectAssignments.results',
                'lessonPlans'
            ])->findOrFail($id);
            
            $gradingData = [
                'subjects' => [],
                'exam_papers' => [],
                'results' => []
            ];
            
            foreach ($teacher->subjectAssignments as $subjectAssignment) {
                $gradingData['subjects'][] = [
                    'id' => $subjectAssignment->id,
                    'subject' => $subjectAssignment->subject,
                    'class' => $subjectAssignment->assigned_class,
                    'section' => $subjectAssignment->assigned_section,
                    'exam_papers_count' => $subjectAssignment->examPapers->count(),
                    'results_count' => $subjectAssignment->results->count(),
                ];
                
                foreach ($subjectAssignment->examPapers as $paper) {
                    $gradingData['exam_papers'][] = [
                        'id' => $paper->id,
                        'title' => $paper->title,
                        'subject' => $paper->subject,
                        'class' => $paper->class,
                        'marks' => $paper->total_marks,
                        'exam_date' => $paper->exam_date,
                        'graded_count' => $paper->results->count(),
                        'total_students' => $paper->enrolledStudents->count(),
                    ];
                }
                
                foreach ($subjectAssignment->results as $result) {
                    $gradingData['results'][] = [
                        'id' => $result->id,
                        'student_name' => $result->student->name,
                        'subject' => $result->exam_paper->subject,
                        'marks_obtained' => $result->marks_obtained,
                        'total_marks' => $result->exam_paper->total_marks,
                        'grade' => $result->grade,
                        'exam_date' => $result->exam_paper->exam_date,
                    ];
                }
            }
            
            return $this->success($gradingData, 'Grading data retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve grading data: ' . $e->getMessage());
        }
    }
}