<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\TeacherClassSubjectAssignment;
use App\Services\ClassTeacherAssignmentService;

class TeacherSubjectAssignmentController extends Controller
{
    /**
     * Section architecture fix: `exists:sections,id` alone only proves the
     * row exists, never that it actually belongs to the class this
     * assignment is FOR -- Sections are globally shared labels (A/B/C/D...)
     * resolved to a class via the legacy_class_map -> class_management ->
     * class_sections bridge (SchoolClass::validSectionIds()), the same
     * pattern already enforced in TimetableController and
     * TeacherSubstitutionController. This table is the source of truth
     * TimetableSlotPolicy::teacherAssignedToClassSection() and
     * GeneratorService both trust for "who's eligible to teach what", so
     * an unvalidated pairing here would corrupt both. A null section_id is
     * this form's own "whole class" semantics and is always valid.
     */
    private function sectionOwnershipError(SchoolClass $schoolClass, ?int $sectionId): ?string
    {
        if ($sectionId === null) {
            return null;
        }

        if (! in_array($sectionId, $schoolClass->validSectionIds(), true)) {
            $section = Section::find($sectionId);
            $sectionName = $section->name ?? 'This section';

            return "\"{$sectionName}\" is not a section of \"{$schoolClass->name}\" -- choose a section that actually belongs to this class.";
        }

        return null;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->authorize('viewAny', TeacherClassSubjectAssignment::class);

        $query = TeacherClassSubjectAssignment::with(['teacher', 'coTeacher', 'subject', 'schoolClass', 'section']);
        
        // Apply filters
        if (request()->has('teacher_id') && request('teacher_id')) {
            $query->where('teacher_id', request('teacher_id'));
        }
        
        if (request()->has('class_id') && request('class_id')) {
            $query->where('class_id', request('class_id'));
        }
        
        if (request()->has('academic_year') && request('academic_year')) {
            $query->where('academic_year', request('academic_year'));
        }
        
        $assignments = $query->orderBy('teacher_id')->paginate(15);
        
        // Get data for filters
        $teachers = Teacher::orderBy('name')->get();
        $classes = SchoolClass::orderBy('name')->get();
        
        return view('admin.assignments.teacher-subject.index', compact('assignments', 'teachers', 'classes'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authorize('create', TeacherClassSubjectAssignment::class);

        $teachers = Teacher::orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        $classes = SchoolClass::orderBy('name')->get();
        $sections = Section::orderBy('name')->get();
        
        return view('admin.assignments.teacher-subject.create', compact('teachers', 'subjects', 'classes', 'sections'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('create', TeacherClassSubjectAssignment::class);

        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'co_teacher_id' => 'nullable|exists:teachers,id|different:teacher_id',
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'academic_year' => 'nullable|string|max:20',
            'is_class_teacher' => 'boolean',
            'is_primary_subject_teacher' => 'boolean',
            'periods_per_week' => 'nullable|integer|min:1|max:12',
            'require_consecutive' => 'boolean',
        ]);

        $sectionError = $this->sectionOwnershipError(
            SchoolClass::findOrFail($request->class_id),
            $request->section_id ? (int) $request->section_id : null
        );
        if ($sectionError) {
            return redirect()->back()->withErrors(['error' => $sectionError])->withInput();
        }

        $academicYear = $request->academic_year ?? date('Y') . '-' . (date('Y') + 1);
        $isClassTeacher = $request->has('is_class_teacher');
        $isPrimarySubjectTeacher = $request->has('is_primary_subject_teacher');
        $requireConsecutive = $request->has('require_consecutive');

        DB::beginTransaction();
        try {
            // Check class teacher limit BEFORE making assignment
            if ($isClassTeacher) {
                // Count existing class teacher assignments for this teacher
                $existingClassTeacherCount = TeacherClassSubjectAssignment::where('teacher_id', $request->teacher_id)
                    ->where('is_class_teacher', true)
                    ->count();
                
                // Maximum 2 class teacher assignments allowed
                if ($existingClassTeacherCount >= 2) {
                    return redirect()->back()
                        ->withErrors(['error' => 'Teacher already assigned as class teacher for maximum allowed classes (2).'])
                        ->withInput();
                }
                
                // Remove existing class teacher for this class/section only
                TeacherClassSubjectAssignment::where('class_id', $request->class_id)
                    ->where('section_id', $request->section_id)
                    ->where('academic_year', $academicYear)
                    ->where('is_class_teacher', true)
                    ->update(['is_class_teacher' => false]);
            }

            // Use updateOrCreate to handle existing assignments
            // IMPORTANT: Only set is_class_teacher if checkbox is checked
            $assignment = TeacherClassSubjectAssignment::updateOrCreate(
                [
                    'teacher_id' => $request->teacher_id,
                    'class_id' => $request->class_id,
                    'section_id' => $request->section_id,
                    'subject_id' => $request->subject_id,
                    'academic_year' => $academicYear,
                ],
                [
                    'co_teacher_id' => $request->co_teacher_id,
                    'is_class_teacher' => $isClassTeacher, // Only set is_class_teacher if checkbox checked // Only set if checkbox checked
                    'is_primary_subject_teacher' => $isPrimarySubjectTeacher,
                    'periods_per_week' => $request->periods_per_week,
                    'require_consecutive' => $requireConsecutive,
                ]
            );

            DB::commit();

            $message = $assignment->wasRecentlyCreated
                ? 'Teacher assigned successfully.'
                : 'Assignment updated successfully.';

            return redirect()->route('admin.teacher-subject-assignments.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withErrors(['error' => 'Error creating assignment: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $assignment = TeacherClassSubjectAssignment::with(['teacher', 'subject', 'schoolClass', 'section'])->findOrFail($id);

        $this->authorize('update', $assignment);

        $teachers = Teacher::orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        $classes = SchoolClass::orderBy('name')->get();
        $sections = Section::orderBy('name')->get();
        
        return view('admin.assignments.teacher-subject.edit', compact('assignment', 'teachers', 'subjects', 'classes', 'sections'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $assignment = TeacherClassSubjectAssignment::findOrFail($id);

        $this->authorize('update', $assignment);

        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'co_teacher_id' => 'nullable|exists:teachers,id|different:teacher_id',
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'academic_year' => 'nullable|string|max:20',
            'is_class_teacher' => 'boolean',
            'is_primary_subject_teacher' => 'boolean',
            'periods_per_week' => 'nullable|integer|min:1|max:12',
            'require_consecutive' => 'boolean',
        ]);

        $sectionError = $this->sectionOwnershipError(
            SchoolClass::findOrFail($request->class_id),
            $request->section_id ? (int) $request->section_id : null
        );
        if ($sectionError) {
            return redirect()->back()->withErrors(['error' => $sectionError])->withInput();
        }

        $isClassTeacher = $request->has('is_class_teacher');
        $isPrimarySubjectTeacher = $request->has('is_primary_subject_teacher');
        $requireConsecutive = $request->has('require_consecutive');

        DB::beginTransaction();
        try {
            // If making class teacher, remove existing class teacher for this class/section
            if ($isClassTeacher) {
                TeacherClassSubjectAssignment::where('class_id', $request->class_id)
                    ->where('section_id', $request->section_id)
                    ->where('academic_year', $request->academic_year ?? $assignment->academic_year)
                    ->where('is_class_teacher', true)
                    ->where('id', '!=', $id) // Exclude current assignment
                    ->update(['is_class_teacher' => false]);
            }

            $assignment->update([
                'teacher_id' => $request->teacher_id,
                'co_teacher_id' => $request->co_teacher_id,
                'class_id' => $request->class_id,
                'section_id' => $request->section_id,
                'subject_id' => $request->subject_id,
                'academic_year' => $request->academic_year,
                'is_class_teacher' => $isClassTeacher, // Only set is_class_teacher if checkbox checked
                'is_primary_subject_teacher' => $isPrimarySubjectTeacher,
                'periods_per_week' => $request->periods_per_week,
                'require_consecutive' => $requireConsecutive,
            ]);

            DB::commit();

            return redirect()->route('admin.teacher-subject-assignments.index')
                ->with('success', 'Assignment updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withErrors(['error' => 'Error updating assignment: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Bulk-create every class/section/subject assignment for one teacher in
     * a single request (the "assign a teacher to 5 classes without opening
     * the form 5 times" flow). Reuses the exact same rules as store():
     * updateOrCreate keyed on teacher+class+section+subject+year, and
     * sectionOwnershipError() so a bulk row can't silently attach a section
     * to a class it doesn't belong to. Class-teacher assignment goes through
     * ClassTeacherAssignmentService rather than a separate table -- that
     * service is the one canonical writer of is_class_teacher (see its
     * docblock), so this bulk endpoint must not bypass it.
     */
    public function bulkStore(Request $request)
    {
        $this->authorize('create', TeacherClassSubjectAssignment::class);

        $validated = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'academic_year' => 'nullable|string|max:20',
            'assignments' => 'required|array|min:1',
            'assignments.*.class_id' => 'required|exists:school_classes,id',
            'assignments.*.section_id' => 'nullable|exists:sections,id',
            'assignments.*.subject_id' => 'required|exists:subjects,id',
            'assignments.*.periods_per_week' => 'nullable|integer|min:1|max:12',
            'make_class_teacher' => 'nullable|boolean',
            'class_teacher_class_id' => 'nullable|exists:school_classes,id',
            'class_teacher_section_id' => 'nullable|exists:sections,id',
        ]);

        $teacherId = (int) $validated['teacher_id'];
        $academicYear = $validated['academic_year'] ?? date('Y') . '-' . (date('Y') + 1);
        $assignments = $validated['assignments'];

        // Validate section ownership for every row up front so a bad row
        // fails the whole batch instead of leaving a partial commit.
        $classesById = [];
        foreach ($assignments as $row) {
            $classId = (int) $row['class_id'];
            $sectionId = isset($row['section_id']) && $row['section_id'] !== '' ? (int) $row['section_id'] : null;

            if (! isset($classesById[$classId])) {
                $classesById[$classId] = SchoolClass::findOrFail($classId);
            }

            $sectionError = $this->sectionOwnershipError($classesById[$classId], $sectionId);
            if ($sectionError) {
                return response()->json(['success' => false, 'error' => $sectionError], 422);
            }
        }

        $makeClassTeacher = (bool) ($validated['make_class_teacher'] ?? false);
        $classTeacherClassId = $makeClassTeacher && ! empty($validated['class_teacher_class_id'])
            ? (int) $validated['class_teacher_class_id']
            : null;
        $classTeacherSectionId = $makeClassTeacher && ! empty($validated['class_teacher_section_id'])
            ? (int) $validated['class_teacher_section_id']
            : null;

        if ($makeClassTeacher) {
            if ($classTeacherClassId === null) {
                return response()->json(['success' => false, 'error' => 'Select a class for the class teacher assignment.'], 422);
            }

            $matchesClassTeacherRow = collect($assignments)->contains(function ($row) use ($classTeacherClassId, $classTeacherSectionId) {
                $rowSectionId = isset($row['section_id']) && $row['section_id'] !== '' ? (int) $row['section_id'] : null;

                return (int) $row['class_id'] === $classTeacherClassId && $rowSectionId === $classTeacherSectionId;
            });

            if (! $matchesClassTeacherRow) {
                return response()->json([
                    'success' => false,
                    'error' => 'The class teacher class/section must match one of the subject assignments above.',
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $teacher = Teacher::findOrFail($teacherId);
            $createdCount = 0;

            foreach ($assignments as $row) {
                $sectionId = isset($row['section_id']) && $row['section_id'] !== '' ? (int) $row['section_id'] : null;

                TeacherClassSubjectAssignment::updateOrCreate(
                    [
                        'teacher_id' => $teacherId,
                        'class_id' => (int) $row['class_id'],
                        'section_id' => $sectionId,
                        'subject_id' => (int) $row['subject_id'],
                        'academic_year' => $academicYear,
                    ],
                    [
                        'periods_per_week' => $row['periods_per_week'] ?? null,
                    ]
                );
                $createdCount++;
            }

            if ($makeClassTeacher) {
                $result = app(ClassTeacherAssignmentService::class)->assign(
                    $classesById[$classTeacherClassId],
                    $classTeacherSectionId,
                    $teacherId,
                    (int) collect($assignments)->firstWhere(fn ($row) => (int) $row['class_id'] === $classTeacherClassId
                        && (isset($row['section_id']) && $row['section_id'] !== '' ? (int) $row['section_id'] : null) === $classTeacherSectionId
                    )['subject_id'],
                    $academicYear
                );

                if (! $result['success']) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'error' => $result['error']], 422);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Created {$createdCount} assignment(s) for {$teacher->name}.",
                'data' => [
                    'teacher_name' => $teacher->name,
                    'assignments_created' => $createdCount,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => 'Failed to create assignments: ' . $e->getMessage()], 422);
        }
    }

    /**
     * Delete multiple assignments in one request (checkbox multi-select on
     * the index list). Authorizes against every row that matched, same as
     * destroy() would for each one individually -- the policy's delete()
     * check isn't row-data-dependent (it's the same admin/manage-permission
     * check as create()), so this can't silently skip an authorization a
     * single delete would have enforced.
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:teacher_class_subject_assignments,id',
        ]);

        $assignments = TeacherClassSubjectAssignment::whereIn('id', $validated['ids'])->get();

        foreach ($assignments as $assignment) {
            $this->authorize('delete', $assignment);
        }

        $count = TeacherClassSubjectAssignment::whereIn('id', $validated['ids'])->delete();

        return response()->json([
            'success' => true,
            'message' => "Deleted {$count} assignment(s).",
            'deleted_count' => $count,
        ]);
    }

    public function destroy($id)
    {
        $assignment = TeacherClassSubjectAssignment::findOrFail($id);

        $this->authorize('delete', $assignment);

        $assignment->delete();
        
        return redirect()->route('admin.teacher-subject-assignments.index')
            ->with('success', 'Assignment removed successfully.');
    }
}