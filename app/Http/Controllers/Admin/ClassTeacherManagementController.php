<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Services\ClassTeacherAssignmentService;
use Illuminate\Http\Request;

/**
 * Academic setup completion: a dedicated, purpose-built screen for the
 * "Select Class -> Select Section -> Select Teacher -> assign as Class
 * Teacher" workflow. Deliberately separate from
 * Admin\TeacherSubjectAssignmentController's generic create/edit form (which
 * requires stepping through co-teacher/periods-per-week/etc. fields
 * irrelevant to this task) -- both write to the exact same canonical table
 * (teacher_class_subject_assignments) via ClassTeacherAssignmentService, so
 * there is no duplicate/competing data store, only a second, simpler entry
 * point onto the same one.
 */
class ClassTeacherManagementController extends Controller
{
    public function __construct(private ClassTeacherAssignmentService $service)
    {
    }

    /**
     * All classes, each with a quick "N of M sections have a class teacher"
     * summary so the admin can see at a glance where coverage is missing.
     */
    public function index()
    {
        $this->authorize('viewAny', TeacherClassSubjectAssignment::class);

        $academicYear = $this->currentAcademicYear();

        $classes = SchoolClass::orderBy('class_order')->get()->map(function (SchoolClass $class) use ($academicYear) {
            $sectionIds = $class->validSectionIds();
            // A null section_id ("whole class") is always a valid slot too.
            $slots = array_merge([null], $sectionIds);

            // A teacher can hold is_class_teacher=true on more than one
            // subject row for the same class+section (one flag per subject
            // they teach there), so this must count distinct slots, not rows.
            $assignedSlots = TeacherClassSubjectAssignment::where('class_id', $class->id)
                ->where('academic_year', $academicYear)
                ->where('is_class_teacher', true)
                ->pluck('section_id')
                ->unique()
                ->intersect($slots)
                ->count();

            return [
                'class' => $class,
                'total_slots' => count($slots),
                'assigned_count' => $assignedSlots,
            ];
        });

        return view('admin.class-teachers.index', compact('classes', 'academicYear'));
    }

    /**
     * One class's valid sections (plus "whole class"), each showing the
     * current class teacher if any, and the assign/change form.
     */
    public function show(SchoolClass $schoolClass, Request $request)
    {
        $this->authorize('viewAny', TeacherClassSubjectAssignment::class);

        $academicYear = $request->query('academic_year', $this->currentAcademicYear());

        $sectionIds = $schoolClass->validSectionIds();
        $sections = $sectionIds ? Section::whereIn('id', $sectionIds)->orderBy('name')->get() : collect();

        $rows = collect([['id' => null, 'name' => 'Whole class (no section)']])
            ->concat($sections->map(fn (Section $s) => ['id' => $s->id, 'name' => $s->name]))
            ->map(function (array $slot) use ($schoolClass, $academicYear) {
                $slot['current'] = $this->service->currentClassTeacher($schoolClass->id, $slot['id'], $academicYear);

                return $slot;
            });

        $teachers = Teacher::orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();

        return view('admin.class-teachers.show', [
            'schoolClass' => $schoolClass,
            'rows' => $rows,
            'teachers' => $teachers,
            'subjects' => $subjects,
            'academicYear' => $academicYear,
        ]);
    }

    public function assign(SchoolClass $schoolClass, Request $request)
    {
        $this->authorize('create', TeacherClassSubjectAssignment::class);

        $validated = $request->validate([
            'section_id' => 'nullable|exists:sections,id',
            'teacher_id' => 'required|exists:teachers,id',
            'subject_id' => 'required|exists:subjects,id',
            'academic_year' => 'required|string|max:20',
        ]);

        $result = $this->service->assign(
            $schoolClass,
            ! empty($validated['section_id']) ? (int) $validated['section_id'] : null,
            (int) $validated['teacher_id'],
            (int) $validated['subject_id'],
            $validated['academic_year']
        );

        if (! $result['success']) {
            return redirect()
                ->route('admin.class-teachers.show', ['schoolClass' => $schoolClass, 'academic_year' => $validated['academic_year']])
                ->with('error', $result['error']);
        }

        return redirect()
            ->route('admin.class-teachers.show', ['schoolClass' => $schoolClass, 'academic_year' => $validated['academic_year']])
            ->with('success', 'Class teacher assigned successfully.');
    }

    public function remove(TeacherClassSubjectAssignment $assignment, Request $request)
    {
        $this->authorize('update', $assignment);

        $this->service->remove($assignment);

        return redirect()
            ->route('admin.class-teachers.show', ['schoolClass' => $assignment->class_id, 'academic_year' => $assignment->academic_year])
            ->with('success', 'Class teacher removed.');
    }

    private function currentAcademicYear(): string
    {
        return date('Y') . '-' . (date('Y') + 1);
    }
}
