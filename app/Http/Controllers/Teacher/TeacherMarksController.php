<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Exam;
use App\Models\GradingSystem;
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

        // Sync-audit loophole L-04: matched by class_name/subject string
        // instead of the enforced class_id/subject_id FKs -- risky given
        // school_classes.name carries no unique constraint (this codebase
        // already hit that exact collision once with the legacy
        // ClassManagement table).
        $exams = Exam::whereIn('class_id', $assignedClassIds)
            ->whereIn('subject_id', $assignedSubjectIds)
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

        // Verify teacher has access to this exam's class and subject.
        // Sync-audit loophole L-04: matched by class_name/subject string
        // instead of exam's own enforced class_id/subject_id FKs -- see
        // index() above for why that's risky.
        $teacherAssignment = TeacherClassSubjectAssignment::where('teacher_id', $teacherId)
            ->where('class_id', $exam->class_id)
            ->where('subject_id', $exam->subject_id)
            ->first();

        if (!$teacherAssignment) {
            return redirect()->back()->with('error', 'You do not have access to upload marks for this exam.');
        }

        $class = SchoolClass::find($exam->class_id);
        if (!$class) {
            return back()->with('error', 'Class not found for this exam.');
        }

        // Get students for this exam's class, filtered to the teacher's
        // specific section if they're assigned to one rather than the
        // whole class.
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

        // Verify teacher has access. Sync-audit loophole L-04: matched by
        // class_name/subject string instead of exam's own enforced
        // class_id/subject_id FKs -- see index() above for why that's risky.
        $hasAccess = TeacherClassSubjectAssignment::where('teacher_id', $teacherId)
            ->where('class_id', $exam->class_id)
            ->where('subject_id', $exam->subject_id)
            ->exists();

        if (!$hasAccess) {
            return back()->with('error', 'You do not have access to upload marks for this exam.');
        }

        $class = SchoolClass::find($exam->class_id);
        if (!$class) {
            return back()->with('error', 'Class not found for this exam.');
        }

        $subject = Subject::find($exam->subject_id);
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

                // Determine pass/fail against the exam's own passing_marks.
                // results.result_status defaults to 'pass' at the schema
                // level and this array never set it, so every mark entered
                // through this route -- the live teacher-facing path --
                // was silently recorded as a pass regardless of the actual
                // score. Matches the same exam.passing_marks-aware pattern
                // already used by Result::updateResultStatus() and
                // Admin\ResultController::store()/update().
                $resultStatus = $marksObtained >= $exam->passing_marks ? 'pass' : 'fail';

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
                        'result_status' => $resultStatus,
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
