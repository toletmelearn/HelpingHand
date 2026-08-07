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

class TeacherSubjectAssignmentController extends Controller
{
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

    public function destroy($id)
    {
        $assignment = TeacherClassSubjectAssignment::findOrFail($id);

        $this->authorize('delete', $assignment);

        $assignment->delete();
        
        return redirect()->route('admin.teacher-subject-assignments.index')
            ->with('success', 'Assignment removed successfully.');
    }
}