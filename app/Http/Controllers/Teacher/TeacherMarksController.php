<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Exam;
use App\Models\Result;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherClassSubjectAssignment;
use Illuminate\Support\Facades\DB;

class TeacherMarksController extends Controller
{
    /**
     * Display list of exams for which teacher can upload marks.
     */
    public function index()
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacherId = $teacherLogin->teacher_id;

        // Get teacher's assigned classes and subjects
        $assignedAssignments = TeacherClassSubjectAssignment::where('teacher_id', $teacherId)
            ->with(['subject', 'schoolClass'])
            ->get();

        $assignedClassIds = $assignedAssignments->pluck('class_id')->unique();
        $assignedSubjectIds = $assignedAssignments->pluck('subject_id')->unique();

        // Get exams for assigned classes
        $exams = Exam::whereIn('class_name', $assignedAssignments->pluck('schoolClass.name')->unique())
            ->whereIn('subject', $assignedAssignments->pluck('subject.name')->unique())
            ->latest()
            ->paginate(15);

        return view('teacher.marks.index', compact('exams', 'assignedAssignments'));
    }

    /**
     * Show general upload marks form.
     */
    public function uploadForm()
    {
        return view('teacher.marks.upload');
    }

    /**
     * Show marks upload form for a specific exam.
     */
    public function show($examId)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacherId = $teacherLogin->teacher_id;
        $exam = Exam::findOrFail($examId);

        // Verify teacher has access to this exam's class and subject
        $hasAccess = TeacherClassSubjectAssignment::where('teacher_id', $teacherId)
            ->whereHas('schoolClass', function ($query) use ($exam) {
                $query->where('name', $exam->class_name);
            })
            ->whereHas('subject', function ($query) use ($exam) {
                $query->where('name', $exam->subject);
            })
            ->exists();

        if (!$hasAccess) {
            return redirect()->back()->with('error', 'You do not have access to upload marks for this exam.');
        }

        // Get the class by name to get the class_id
        $class = SchoolClass::where('name', $exam->class_name)->first();
        if (!$class) {
            return back()->with('error', 'Class not found for this exam.');
        }
        
        // Get students for this exam's class
        // First, get the teacher's assignment to see if they're assigned to a specific section
        $teacherAssignment = TeacherClassSubjectAssignment::where('teacher_id', $teacherId)
            ->whereHas('schoolClass', function ($query) use ($exam) {
                $query->where('name', $exam->class_name);
            })
            ->whereHas('subject', function ($query) use ($exam) {
                $query->where('name', $exam->subject);
            })
            ->first();
        
        $studentsQuery = Student::where('school_class_id', $class->id);
        
        // If teacher is assigned to a specific section, filter by that section
        if ($teacherAssignment && $teacherAssignment->section_id) {
            $studentsQuery->where('section_id', $teacherAssignment->section_id);
        }
        
        $students = $studentsQuery->orderBy('roll_number')->get();

        // Get existing results
        $existingResults = Result::where('exam_id', $examId)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        return view('teacher.marks.upload', compact('exam', 'students', 'existingResults'));
    }

    /**
     * Store or update marks for students.
     */
    public function store(Request $request)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacherId = $teacherLogin->teacher_id;

        $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'marks' => 'required|array',
            'marks.*.student_id' => 'required|exists:students,id',
            'marks.*.theory_marks' => 'nullable|numeric|min:0',
            'marks.*.practical_marks' => 'nullable|numeric|min:0',
            'marks.*.marks_obtained' => 'nullable|numeric|min:0',
            'marks.*.status' => 'nullable|string',
        ]);

        $exam = Exam::findOrFail($request->exam_id);

        // Verify teacher has access
        $hasAccess = TeacherClassSubjectAssignment::where('teacher_id', $teacherId)
            ->whereHas('schoolClass', function ($query) use ($exam) {
                $query->where('name', $exam->class_name);
            })
            ->whereHas('subject', function ($query) use ($exam) {
                $query->where('name', $exam->subject);
            })
            ->exists();

        if (!$hasAccess) {
            return back()->with('error', 'You do not have access to upload marks for this exam.');
        }

        // Get class_id from class_name
        $class = SchoolClass::where('name', $exam->class_name)->first();
        if (!$class) {
            return back()->with('error', 'Class not found for this exam.');
        }

        // Get subject_id from subject name
        $subject = Subject::where('name', $exam->subject)->first();
        if (!$subject) {
            return back()->with('error', 'Subject not found for this exam.');
        }

        DB::beginTransaction();
        try {
            foreach ($request->marks as $markData) {
                // Calculate marks obtained from theory and practical, or use marks_obtained if provided
                $theoryMarks = $markData['theory_marks'] ?? 0;
                $practicalMarks = $markData['practical_marks'] ?? 0;
                $marksObtained = $markData['marks_obtained'] ?? ($theoryMarks + $practicalMarks);

                if ($marksObtained > $exam->total_marks) {
                    DB::rollback();
                    return back()->withInput()->with('error', 'Marks obtained cannot exceed total marks (' . $exam->total_marks . ') for student ID: ' . $markData['student_id']);
                }

                $existing = Result::where('student_id', $markData['student_id'])
                    ->where('exam_id', $request->exam_id)
                    ->first();

                if ($existing && ($existing->is_locked || $existing->is_verified)) {
                    continue; // Skip locked/verified marks - do not silently overwrite
                }

                $percentage = $exam->total_marks > 0 ? ($marksObtained / $exam->total_marks) * 100 : 0;

                // Determine grade
                $grade = $this->calculateGrade($percentage);

                // Get status, defaulting to 'present'
                $status = $markData['status'] ?? 'present';

                // Create or update result
                $result = Result::updateOrCreate(
                    [
                        'student_id' => $markData['student_id'],
                        'exam_id' => $request->exam_id,
                    ],
                    [
                        'subject' => $exam->subject,
                        'class_id' => $class->id,
                        'subject_id' => $subject->id,
                        'marks_obtained' => $marksObtained,
                        'total_marks' => $exam->total_marks,
                        'percentage' => $percentage,
                        'grade' => $grade,
                        'academic_year' => $exam->academic_year,
                        'uploaded_by_teacher_id' => $teacherId,
                        'uploaded_at' => now(),
                        'status' => 'submitted',
                        'is_locked' => true, // Lock marks after submission
                    ]
                );

                // Verify the result was saved
                if (!$result) {
                    DB::rollback();
                    return back()->with('error', 'Failed to save marks for student ID: ' . $markData['student_id']);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Error saving marks: ' . $e->getMessage());
        }

        // Verify marks were actually saved in database
        $savedCount = Result::where('exam_id', $request->exam_id)->count();
        
        if ($savedCount == 0) {
            return back()->with('error', 'Failed to save marks. No records found in database.');
        }

        return redirect()->route('teacher.marks.index')
            ->with('success', 'Marks uploaded successfully! ' . $savedCount . ' student marks saved.');
    }

    /**
     * Calculate grade based on percentage.
     */
    private function calculateGrade($percentage)
    {
        if ($percentage >= 91) return 'A1';
        if ($percentage >= 81) return 'A2';
        if ($percentage >= 71) return 'B1';
        if ($percentage >= 61) return 'B2';
        if ($percentage >= 51) return 'C1';
        if ($percentage >= 41) return 'C2';
        if ($percentage >= 33) return 'D';
        return 'F';
    }
}
