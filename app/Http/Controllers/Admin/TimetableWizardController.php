<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Services\Timetable\FeasibilityService;
use App\Services\Timetable\GeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * T6 item 5 (revised): "Set Up Timetable" -- one guided flow collapsing the
 * separate subject-assignment / style / readiness screens into a single
 * walkthrough for a non-technical admin. UI orchestration only -- every
 * step writes through the SAME tables and calls the SAME services the
 * individual admin pages already use.
 *
 * Section-awareness revision: the original Step 1 ("who is this class's
 * teacher") operated at the CLASS level only and stored its pick on
 * school_classes.teacher_id -- a single column that cannot hold two
 * different picks for two sections of the same class (e.g. III-A/Prashant
 * Patra vs III-B/Lalita Devi, the real Pushp Niketan structure). That
 * column is no longer read or written by the wizard at all (left alone in
 * the schema, per instruction -- some other consumer may still reference
 * it later, but this controller doesn't).
 *
 * The standalone Step 1 screen is gone. What was Step 2 (the per-class
 * subject grid, already fully section-aware via
 * teacher_class_subject_assignments.section_id -- see
 * TeacherSubjectAssignmentController for the pattern this mirrors) is now
 * Step 1, walked one CLASS-SECTION at a time instead of one class at a
 * time. The class-teacher pick is just the existing per-row
 * "is_class_teacher" checkbox, made once the subject is also known --
 * there is no separate provisional-pick step or scratch memory to keep in
 * sync anymore.
 *
 * Section discovery (a design decision, not a schema change -- there is no
 * canonical "which sections belong to this class" table; `sections` is a
 * flat global label list, see Section model): a class's sections are
 * whatever section_ids already appear for it in
 * teacher_class_subject_assignments or students.school_class_id. A class
 * with neither (Nursery/LKG/UKG, or any class nobody has split into
 * sections) gets a single class-wide row (section_id null), exactly the
 * pre-revision behaviour. An admin can always add a not-yet-used section
 * to any class via the "Add a section" picker on Step 1 -- it reuses the
 * same flat Section list the standalone assignment form already offers,
 * and simply navigates to that class+section's (currently empty) grid;
 * once a row is saved there, it's real data and shows up in the sequence
 * on the next page load.
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

    /** Landing page: jump straight to the first class-section in the sequence. */
    public function index()
    {
        $this->authorizeWizard();

        $sequence = $this->buildSequence();
        $first = $sequence->first();

        if (! $first) {
            return view('admin.timetable.wizard.empty');
        }

        return redirect()->to($this->stepOneUrl($first['class'], $first['section_id']));
    }

    /**
     * STEP 1 -- Subjects & Class Teachers, one class-section at a time: a
     * subject/teacher/periods grid, with the class-teacher's row being
     * whichever row has "Class Teacher's Subject" checked. Nothing is
     * pre-filled that can't be read back from real, already-saved data.
     */
    public function step1(SchoolClass $class, ?Section $section = null)
    {
        $this->authorizeWizard();

        $sectionId = $section?->id;
        $sequence = $this->buildSequence();
        $position = $this->resolvePosition($sequence, $class, $sectionId);

        $academicYear = $this->currentAcademicYear();
        $assignments = TeacherClassSubjectAssignment::with(['teacher', 'subject'])
            ->where('class_id', $class->id)
            ->where('section_id', $sectionId)
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->get();

        $subjects = Subject::active()->orderBy('name')->get();
        $teachers = Teacher::active()->orderBy('name')->get();
        $allSections = Section::orderBy('name')->get();

        $next = $sequence->get($position + 1);
        $previous = $position > 0 ? $sequence->get($position - 1) : null;
        $stepLabel = 'Class-section ' . ($position + 1) . ' of ' . $sequence->count();

        return view('admin.timetable.wizard.step1', compact(
            'class', 'section', 'sectionId', 'assignments', 'subjects', 'teachers',
            'allSections', 'next', 'previous', 'stepLabel', 'academicYear'
        ));
    }

    public function step1Store(Request $request, SchoolClass $class, ?Section $section = null)
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

        $sectionId = $section?->id;
        $academicYear = $this->currentAcademicYear() ?? (date('Y') . '-' . (date('Y') + 1));
        $rows = collect($request->input('rows', []))
            ->filter(fn ($row) => !empty($row['subject_id']) && !empty($row['teacher_id']));

        DB::transaction(function () use ($rows, $class, $sectionId, $academicYear) {
            foreach ($rows as $row) {
                $isClassTeacher = !empty($row['is_class_teacher']);

                if ($isClassTeacher) {
                    // Same one-class-teacher-row-per-class-SECTION rule the
                    // standalone assignment form enforces.
                    TeacherClassSubjectAssignment::where('class_id', $class->id)
                        ->where('section_id', $sectionId)
                        ->where('academic_year', $academicYear)
                        ->where('is_class_teacher', true)
                        ->update(['is_class_teacher' => false]);
                }

                TeacherClassSubjectAssignment::updateOrCreate(
                    [
                        'teacher_id' => $row['teacher_id'],
                        'class_id' => $class->id,
                        'section_id' => $sectionId,
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

        $sequence = $this->buildSequence();
        $position = $this->resolvePosition($sequence, $class, $sectionId);
        $next = $sequence->get($position + 1);

        $label = $class->name . ($section ? " ({$section->name})" : '');

        return $next
            ? redirect()->to($this->stepOneUrl($next['class'], $next['section_id']))->with('success', "Saved {$label}.")
            : redirect()->route('timetable.wizard.step2')->with('success', "Saved {$label}. All class-sections done.");
    }

    /** STEP 2 -- Style: fixed_daily vs rotating, one plain-language sentence each. */
    public function step2()
    {
        $this->authorizeWizard();

        $sequence = $this->buildSequence();
        $first = $sequence->first();

        return view('admin.timetable.wizard.step2', ['firstUrl' => $first ? $this->stepOneUrl($first['class'], $first['section_id']) : null]);
    }

    /**
     * STEP 3 -- Review readiness: FeasibilityService's own report, in
     * plain language, BEFORE generating. Never blocks -- generation is
     * reachable regardless of what this shows, exactly like the
     * standalone feasibility page never blocks Generate today.
     */
    public function step3(Request $request, FeasibilityService $service)
    {
        $this->authorizeWizard();

        $style = in_array($request->query('style'), [GeneratorService::STYLE_ROTATING, GeneratorService::STYLE_FIXED_DAILY], true)
            ? $request->query('style')
            : GeneratorService::STYLE_ROTATING;

        $academicYear = $this->currentAcademicYear();
        $report = $service->build($academicYear);

        return view('admin.timetable.wizard.step3', compact('report', 'style'));
    }

    /** @return string a fully-qualified Step 1 URL for a given class(+section). */
    private function stepOneUrl(SchoolClass $class, ?int $sectionId): string
    {
        return $sectionId
            ? route('timetable.wizard.step1', [$class, $sectionId])
            : route('timetable.wizard.step1', [$class]);
    }

    /**
     * The full, ordered class-section walkthrough sequence: every active
     * class, each split into one entry per section actually in use for it
     * (or a single section_id-null entry if none are).
     *
     * @return Collection<int, array{class: SchoolClass, section_id: ?int}>
     */
    private function buildSequence(): Collection
    {
        return SchoolClass::active()->orderByOrder()->get()
            ->flatMap(function (SchoolClass $class) {
                $sectionIds = $this->sectionIdsForClass($class);

                if ($sectionIds->isEmpty()) {
                    return [['class' => $class, 'section_id' => null]];
                }

                return $sectionIds->map(fn ($sectionId) => ['class' => $class, 'section_id' => $sectionId])->all();
            })
            ->values();
    }

    /** @return Collection<int, int> section_ids actually in use for this class, ordered by section name. */
    private function sectionIdsForClass(SchoolClass $class): Collection
    {
        $ids = TeacherClassSubjectAssignment::where('class_id', $class->id)
            ->whereNotNull('section_id')
            ->distinct()
            ->pluck('section_id')
            ->merge(
                Student::where('school_class_id', $class->id)->whereNotNull('section_id')->distinct()->pluck('section_id')
            )
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return $ids;
        }

        return Section::whereIn('id', $ids)->orderBy('name')->pluck('id');
    }

    /**
     * Finds this class+section's position in the sequence, inserting it
     * (mutating $sequence) if it's a not-yet-saved class-section reached
     * via the "Add a section" picker -- so Next/Previous and the progress
     * count stay sane even before any row has been saved for it.
     */
    private function resolvePosition(Collection $sequence, SchoolClass $class, ?int $sectionId): int
    {
        $position = $sequence->search(fn ($row) => $row['class']->id === $class->id && $row['section_id'] === $sectionId);
        if ($position !== false) {
            return $position;
        }

        $insertAt = $sequence->search(fn ($row) => $row['class']->id === $class->id);
        $insertAt = $insertAt === false ? $sequence->count() : $insertAt;
        $sequence->splice($insertAt, 0, [['class' => $class, 'section_id' => $sectionId]]);

        return $insertAt;
    }
}
