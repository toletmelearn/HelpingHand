<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Services\Timetable\FeasibilityService;
use App\Services\Timetable\GeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * T6 item 5: "Set Up Timetable" -- one guided flow collapsing the separate
 * class-teacher / subject-assignment / style / readiness / generate screens
 * into a single walkthrough for a non-technical admin. This is a UI
 * orchestration layer only -- every step writes through the SAME tables
 * and calls the SAME services the individual admin pages already use
 * (TeacherClassSubjectAssignment for subjects, FeasibilityService for
 * readiness, the existing timetable.generate/generation.review/publish
 * flow for Step 5), nothing is reimplemented. Admin-only throughout,
 * gated on TimetableSlotPolicy::generate() -- the same gate the rest of
 * the Generate flow already uses.
 *
 * Data-model choice for Step 1 (a schema question, resolved without a new
 * migration): "who is provisionally this class's teacher, before we know
 * their subject" needs a signal Step 2 can read back to pre-fill/pre-flag
 * the right grid row. The two REAL class-teacher signals GeneratorService/
 * FeasibilityService read (teacher_class_subject_assignments.
 * is_class_teacher and the legacy class_teacher_assignments table, see T6
 * item 1's report) are both unusable here: the first requires a subject
 * to already exist (impossible before Step 2 runs), the second is keyed
 * by users.id, not teachers.id (a confirmed bug, worked around when
 * READING that table but not something new code should build on). This
 * wizard instead uses `school_classes.teacher_id` -- an existing, already
 * correctly Teacher-FK'd column that was sitting unused everywhere else
 * in the codebase (only ever bound to a form field, never read by any
 * service) -- purely as the wizard's own working memory of Step 1's pick.
 * It is NOT read by GeneratorService or FeasibilityService's readiness
 * check; Step 2 is the only consumer, and Step 2's save is what creates
 * the REAL is_class_teacher-flagged assignment row those services do
 * read. No existing read semantics change.
 */
class TimetableWizardController extends Controller
{
    private function authorizeWizard(): void
    {
        $this->authorize('generate', \App\Models\TimetableSlot::class);
    }

    private function currentAcademicYear(): ?string
    {
        return AcademicSession::current()->first()?->code;
    }

    /** STEP 1 -- Class Teachers: every active class, one teacher dropdown each, one Save. */
    public function step1()
    {
        $this->authorizeWizard();

        $classes = SchoolClass::active()->orderByOrder()->with('teacher')->get();
        $teachers = Teacher::active()->orderBy('name')->get();

        return view('admin.timetable.wizard.step1', compact('classes', 'teachers'));
    }

    public function step1Store(Request $request)
    {
        $this->authorizeWizard();

        $request->validate([
            'class_teachers' => 'array',
            'class_teachers.*' => 'nullable|integer|exists:teachers,id',
        ]);

        $classTeachers = $request->input('class_teachers', []);

        foreach (SchoolClass::active()->get() as $class) {
            if (array_key_exists($class->id, $classTeachers)) {
                $class->update(['teacher_id' => $classTeachers[$class->id] ?: null]);
            }
        }

        $firstClass = SchoolClass::active()->orderByOrder()->first();

        return $firstClass
            ? redirect()->route('timetable.wizard.step2', $firstClass)->with('success', 'Class teachers saved.')
            : redirect()->route('timetable.wizard.step3')->with('success', 'Class teachers saved.');
    }

    /**
     * STEP 2 -- Subject Assignments: one class at a time ("Class N of M"),
     * a subject/teacher/periods grid. If this class has a Step 1 teacher
     * and no is_class_teacher row yet, a pre-filled top row anchors their
     * period-1 subject.
     */
    public function step2(SchoolClass $class)
    {
        $this->authorizeWizard();

        $classes = SchoolClass::active()->orderByOrder()->get();
        $position = $classes->search(fn ($c) => $c->id === $class->id);
        if ($position === false) {
            abort(404);
        }

        $academicYear = $this->currentAcademicYear();
        $assignments = TeacherClassSubjectAssignment::with(['teacher', 'subject'])
            ->where('class_id', $class->id)
            ->whereNull('section_id')
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        $hasClassTeacherRow = $assignments->contains('is_class_teacher', true);

        $subjects = Subject::active()->orderBy('name')->get();
        $teachers = Teacher::active()->orderBy('name')->get();

        $next = $classes->get($position + 1);
        $previous = $position > 0 ? $classes->get($position - 1) : null;
        $stepLabel = 'Class ' . ($position + 1) . ' of ' . $classes->count();

        return view('admin.timetable.wizard.step2', compact(
            'class', 'assignments', 'hasClassTeacherRow', 'subjects', 'teachers', 'next', 'previous', 'stepLabel', 'academicYear'
        ));
    }

    public function step2Store(Request $request, SchoolClass $class)
    {
        $this->authorizeWizard();

        $request->validate([
            'rows' => 'array',
            'rows.*.subject_id' => 'nullable|integer|exists:subjects,id',
            'rows.*.teacher_id' => 'nullable|integer|exists:teachers,id',
            'rows.*.periods_per_week' => 'nullable|integer|min:1|max:12',
            'rows.*.is_class_teacher' => 'nullable|boolean',
            'rows.*.require_consecutive' => 'nullable|boolean',
        ]);

        $academicYear = $this->currentAcademicYear() ?? (date('Y') . '-' . (date('Y') + 1));
        $rows = collect($request->input('rows', []))
            ->filter(fn ($row) => !empty($row['subject_id']) && !empty($row['teacher_id']));

        DB::transaction(function () use ($rows, $class, $academicYear) {
            foreach ($rows as $row) {
                $isClassTeacher = !empty($row['is_class_teacher']);

                if ($isClassTeacher) {
                    // Same one-class-teacher-row-per-class rule the
                    // standalone assignment form enforces.
                    TeacherClassSubjectAssignment::where('class_id', $class->id)
                        ->whereNull('section_id')
                        ->where('academic_year', $academicYear)
                        ->where('is_class_teacher', true)
                        ->update(['is_class_teacher' => false]);
                }

                TeacherClassSubjectAssignment::updateOrCreate(
                    [
                        'teacher_id' => $row['teacher_id'],
                        'class_id' => $class->id,
                        'section_id' => null,
                        'subject_id' => $row['subject_id'],
                        'academic_year' => $academicYear,
                    ],
                    [
                        'is_class_teacher' => $isClassTeacher,
                        'periods_per_week' => $row['periods_per_week'] ?? null,
                        'require_consecutive' => !empty($row['require_consecutive']),
                    ]
                );
            }
        });

        $classes = SchoolClass::active()->orderByOrder()->get();
        $position = $classes->search(fn ($c) => $c->id === $class->id);
        $next = $classes->get($position + 1);

        return $next
            ? redirect()->route('timetable.wizard.step2', $next)->with('success', "Saved {$class->name}.")
            : redirect()->route('timetable.wizard.step3')->with('success', "Saved {$class->name}. All classes done.");
    }

    /** STEP 3 -- Style: fixed_daily vs rotating, one plain-language sentence each. */
    public function step3()
    {
        $this->authorizeWizard();

        $firstClass = SchoolClass::active()->orderByOrder()->first();

        return view('admin.timetable.wizard.step3', compact('firstClass'));
    }

    /**
     * STEP 4 -- Review readiness: FeasibilityService's own report, in
     * plain language, BEFORE generating. Never blocks -- Step 5 is
     * reachable regardless of what this shows, exactly like the
     * standalone feasibility page never blocks Generate today.
     */
    public function step4(Request $request, FeasibilityService $service)
    {
        $this->authorizeWizard();

        $style = in_array($request->query('style'), [GeneratorService::STYLE_ROTATING, GeneratorService::STYLE_FIXED_DAILY], true)
            ? $request->query('style')
            : GeneratorService::STYLE_ROTATING;

        $academicYear = $this->currentAcademicYear();
        $report = $service->build($academicYear);

        return view('admin.timetable.wizard.step4', compact('report', 'style'));
    }
}
