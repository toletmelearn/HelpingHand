<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\ClassManagement;
use App\Models\TeacherSubjectAssignment;

class TeacherSubjectAssignmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Get all teacher-subject assignments with related data
        $assignments = TeacherSubjectAssignment::with(['teacher', 'subject', 'class'])
            ->orderBy('teacher_id')
            ->paginate(10);
        
        return view('admin.assignments.teacher-subject.index', compact('assignments'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $teachers = Teacher::orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        $classes = ClassManagement::orderBy('name')->get();
        
        return view('admin.assignments.teacher-subject.create', compact('teachers', 'subjects', 'classes'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'subject_ids' => 'required|array',
            'subject_ids.*' => 'exists:subjects,id',
            'class_ids' => 'required|array',
            'class_ids.*' => 'exists:class_managements,id',
        ]);

        // Create assignments
        foreach ($request->subject_ids as $subjectId) {
            foreach ($request->class_ids as $classId) {
                // Check if assignment already exists
                $existing = TeacherSubjectAssignment::where('teacher_id', $request->teacher_id)
                    ->where('subject_id', $subjectId)
                    ->where('class_id', $classId)
                    ->first();

                if (!$existing) {
                    TeacherSubjectAssignment::create([
                        'teacher_id' => $request->teacher_id,
                        'subject_id' => $subjectId,
                        'class_id' => $classId,
                        'assigned_at' => now(),
                    ]);
                }
            }
        }

        return redirect()->route('admin.teacher-subject-assignments.index')
            ->with('success', 'Teacher-subject assignments created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $assignment = TeacherSubjectAssignment::with(['teacher', 'subject', 'class'])->findOrFail($id);
        $teachers = Teacher::orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        $classes = ClassManagement::orderBy('name')->get();
        
        return view('admin.assignments.teacher-subject.edit', compact('assignment', 'teachers', 'subjects', 'classes'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'subject_id' => 'required|exists:subjects,id',
            'class_id' => 'required|exists:school_classes,id',
            'is_primary' => 'boolean',
        ]);

        $assignment = TeacherSubjectAssignment::findOrFail($id);
        $assignment->update($request->only(['teacher_id', 'subject_id', 'class_id', 'is_primary']));

        return redirect()->route('admin.teacher-subject-assignments.index')
            ->with('success', 'Assignment updated successfully.');
    }

    public function destroy($id)
    {
        $assignment = TeacherSubjectAssignment::findOrFail($id);
        $assignment->delete();
        
        return redirect()->route('admin.teacher-subject-assignments.index')
            ->with('success', 'Assignment removed successfully.');
    }
}