<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Services\Exam\ExamDependencyChecker;
use App\Services\Exam\ExamWriteValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamController extends Controller
{
    public function __construct(
        private ExamDependencyChecker $dependencyChecker,
        private ExamWriteValidator $writeValidator,
    ) {
        $this->middleware('auth');
    }

    /**
     * Sync-audit loophole L-01: nothing ever checked that a class/subject
     * pair being examined was actually part of that class's curriculum --
     * an exam could be created for a subject the class never had a
     * teacher assigned for. Deliberately advisory, not a hard block:
     * test_exam_create_with_a_valid_class_id_derives_class_name_
     * automatically (ExamClassIdRepointTest) creates a brand-new class
     * with zero assignments and expects the exam to be created
     * successfully -- schools legitimately schedule exams before
     * finishing teacher-assignment setup. Matches the same
     * advisory-not-blocking precedent DatesheetConflictChecker::
     * subjectHasNoAssignedTeacher() already established for the same
     * scenario.
     */
    private function curriculumWarning(int $classId, int $subjectId): ?string
    {
        $assigned = TeacherClassSubjectAssignment::where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->exists();

        return $assigned ? null : 'No teacher is currently assigned to teach this subject for this class -- the exam was still created.';
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Exam::class);
        $exams = Exam::with(['createdBy'])->paginate(10);
        return view('admin.exams.index', compact('exams'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Exam::class);
        $classes = SchoolClass::active()->orderByOrder()->get();
        $subjects = Subject::all();
        $teachers = Teacher::all();
        return view('admin.exams.create', compact('classes', 'subjects', 'teachers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Exam::class);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'exam_type' => 'required|string|max:100',
            'class_id' => 'required|exists:school_classes,id',
            'subject' => 'required|string|max:100|exists:subjects,name',
            'exam_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'total_marks' => 'required|numeric|min:0',
            'passing_marks' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'academic_year' => 'required|string|max:20',
            'term' => 'required|string|max:50',
            'status' => 'required|in:active,scheduled,ongoing,cancelled,completed'
        ]);

        // Validate that passing marks don't exceed total marks
        if (!$this->writeValidator->marksValid((float) $request->total_marks, (float) $request->passing_marks)) {
            return redirect()->back()->withErrors(['passing_marks' => 'Passing marks cannot be greater than total marks.']);
        }

        // Duplicate prevention: the same class already has an exam for
        // this subject/term/academic year -- never silently created a
        // second one before.
        if ($this->writeValidator->duplicateExists((int) $request->class_id, $request->subject, $request->academic_year, $request->term)) {
            return redirect()->back()->withInput()->withErrors([
                'subject' => 'An exam for this class, subject, term, and academic year already exists.',
            ]);
        }

        // Normalize status value to match database enum
        $normalizedStatus = $request->status === 'active' ? 'scheduled' : $request->status;

        // class_name/subject_id are derived from the chosen class_id/subject
        // name, never trusted from the request. class_name is kept
        // populated for backward display compatibility with readers that
        // still show the free-text label. subject_id was never written
        // here before -- every admin-created exam had a NULL subject_id
        // until this fix (see 2026_08_29_120100_backfill_exams_subject_id_
        // from_subject_name migration for the historical cleanup).
        $schoolClass = SchoolClass::findOrFail($request->class_id);
        $subjectModel = Subject::where('name', $request->subject)->firstOrFail();

        Exam::create(array_merge(
            $request->except(['status', 'class_name']),
            [
                'class_name' => $schoolClass->name,
                'subject_id' => $subjectModel->id,
                'status' => $normalizedStatus,
                'created_by' => Auth::id()
            ]
        ));

        $warning = $this->curriculumWarning($schoolClass->id, $subjectModel->id);

        return redirect()->route('admin.exams.index')
                         ->with('success', 'Exam created successfully.' . ($warning ? " {$warning}" : ''));
    }

    /**
     * Display the specified resource.
     */
    public function show(Exam $exam)
    {
        $this->authorize('view', $exam);
        return view('admin.exams.show', compact('exam'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Exam $exam)
    {
        $this->authorize('update', $exam);
        $classes = SchoolClass::active()->orderByOrder()->get();
        $subjects = Subject::all();
        $teachers = Teacher::all();
        return view('admin.exams.edit', compact('exam', 'classes', 'subjects', 'teachers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Exam $exam)
    {
        $this->authorize('update', $exam);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'exam_type' => 'required|string|max:100',
            'class_id' => 'required|exists:school_classes,id',
            'subject' => 'required|string|max:100|exists:subjects,name',
            'exam_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'total_marks' => 'required|numeric|min:0',
            'passing_marks' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'academic_year' => 'required|string|max:20',
            'term' => 'required|string|max:50',
            'status' => 'required|in:active,scheduled,ongoing,cancelled,completed'
        ]);

        // Validate that passing marks don't exceed total marks
        if (!$this->writeValidator->marksValid((float) $request->total_marks, (float) $request->passing_marks)) {
            return redirect()->back()->withErrors(['passing_marks' => 'Passing marks cannot be greater than total marks.']);
        }

        // Data integrity: once any result has been recorded against this
        // exam, its class/subject/grading basis must not silently change
        // underneath already-entered marks -- name/date/time/description/
        // status remain freely editable (correcting a typo or rescheduling
        // is fine; changing what the exam even IS or is graded out of is
        // not).
        $dependencies = $this->dependencyChecker->check($exam->id);
        if ($this->dependencyChecker->hasRecordedMarks($dependencies)) {
            $lockedFieldChanged = (int) $request->class_id !== (int) $exam->class_id
                || $request->subject !== $exam->subject
                || (float) $request->total_marks !== (float) $exam->total_marks
                || (float) $request->passing_marks !== (float) $exam->passing_marks;

            if ($lockedFieldChanged) {
                return redirect()->back()->withInput()->withErrors([
                    'total_marks' => 'This exam already has ' . $this->dependencyChecker->summarize($dependencies)
                        . ' recorded -- class, subject, and marks cannot be changed. Only name, date, time, description, and status may still be edited.',
                ]);
            }
        }

        // Normalize status value to match database enum
        $normalizedStatus = $request->status === 'active' ? 'scheduled' : $request->status;

        $schoolClass = SchoolClass::findOrFail($request->class_id);
        $subjectModel = Subject::where('name', $request->subject)->firstOrFail();

        $exam->update(array_merge(
            $request->except(['status', 'class_name']),
            [
                'class_name' => $schoolClass->name,
                'subject_id' => $subjectModel->id,
                'status' => $normalizedStatus,
            ]
        ));

        $warning = $this->curriculumWarning($schoolClass->id, $subjectModel->id);

        return redirect()->route('admin.exams.index')
                         ->with('success', 'Exam updated successfully.' . ($warning ? " {$warning}" : ''));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Exam $exam)
    {
        $this->authorize('delete', $exam);

        // results/cbse_results/exam_papers/exam_blueprints/admit_cards/
        // exam_seating_arrangements all cascade-delete on exams.id --
        // never let delete() reach the DB unguarded, or a single click
        // silently wipes every student's recorded marks for this exam.
        $dependencies = $this->dependencyChecker->check($exam->id);
        if ($this->dependencyChecker->isBlocked($dependencies)) {
            return redirect()->route('admin.exams.index')
                ->with('error', 'Cannot delete this exam -- it is currently used by '
                    . $this->dependencyChecker->summarize($dependencies) . '. Resolve these dependencies before deleting.');
        }

        $exam->delete();

        return redirect()->route('admin.exams.index')
                         ->with('success', 'Exam deleted successfully.');
    }
    
    /**
     * Get exam details for AJAX requests
     */
    public function getExamDetails(Exam $exam)
    {
        return response()->json([
            'subject' => $exam->subject,
            'total_marks' => $exam->total_marks,
            'passing_marks' => $exam->passing_marks,
            'name' => $exam->name
        ]);
    }
}
