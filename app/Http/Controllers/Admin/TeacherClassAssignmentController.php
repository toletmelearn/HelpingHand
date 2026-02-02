<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Teacher;
use App\Models\ClassManagement;
use App\Models\TeacherClassAssignment;

class TeacherClassAssignmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Get all teacher-class assignments with related data
        $assignments = TeacherClassAssignment::with(['teacher', 'class'])
            ->orderBy('class_id')
            ->paginate(10);
        
        return view('admin.assignments.teacher-class.index', compact('assignments'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $teachers = Teacher::orderBy('name')->get();
        $classes = ClassManagement::orderBy('name')->get();
        
        return view('admin.assignments.teacher-class.create', compact('teachers', 'classes'));
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
            'class_id' => 'required|exists:class_managements,id',
            'role' => 'required|in:class_teacher,subject_teacher,assistant_teacher',
        ]);

        // Check if assignment already exists
        $existing = TeacherClassAssignment::where('teacher_id', $request->teacher_id)
            ->where('class_id', $request->class_id)
            ->where('role', $request->role)
            ->first();

        if ($existing) {
            return redirect()->back()
                ->with('error', 'This assignment already exists.')
                ->withInput();
        }

        // For class teachers, ensure only one per class
        if ($request->role === 'class_teacher') {
            TeacherClassAssignment::where('class_id', $request->class_id)
                ->where('role', 'class_teacher')
                ->delete();
        }

        TeacherClassAssignment::create([
            'teacher_id' => $request->teacher_id,
            'class_id' => $request->class_id,
            'role' => $request->role,
            'subject_assigned' => $request->subject_assigned,
            'is_primary' => $request->has('is_primary'),
            'assigned_at' => now(),
        ]);

        return redirect()->route('admin.teacher-class-assignments.index')
            ->with('success', 'Teacher-class assignment created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $assignment = TeacherClassAssignment::with(['teacher', 'class'])->findOrFail($id);
        $teachers = Teacher::orderBy('name')->get();
        $classes = ClassManagement::orderBy('name')->get();
        
        return view('admin.assignments.teacher-class.edit', compact('assignment', 'teachers', 'classes'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'class_id' => 'required|exists:class_managements,id',
            'role' => 'required|in:class_teacher,subject_teacher,assistant_teacher',
            'subject_assigned' => 'nullable|string|max:255',
            'is_primary' => 'boolean',
        ]);

        $assignment = TeacherClassAssignment::findOrFail($id);
        
        // Check if assignment already exists (excluding current)
        $existing = TeacherClassAssignment::where('teacher_id', $request->teacher_id)
            ->where('class_id', $request->class_id)
            ->where('role', $request->role)
            ->where('id', '!=', $id)
            ->first();

        if ($existing) {
            return redirect()->back()
                ->with('error', 'This assignment already exists.')
                ->withInput();
        }

        // For class teachers, ensure only one per class
        if ($request->role === 'class_teacher') {
            TeacherClassAssignment::where('class_id', $request->class_id)
                ->where('role', 'class_teacher')
                ->where('id', '!=', $id)
                ->delete();
        }

        $assignment->update([
            'teacher_id' => $request->teacher_id,
            'class_id' => $request->class_id,
            'role' => $request->role,
            'subject_assigned' => $request->subject_assigned,
            'is_primary' => $request->has('is_primary'),
        ]);

        return redirect()->route('admin.teacher-class-assignments.index')
            ->with('success', 'Assignment updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $assignment = TeacherClassAssignment::findOrFail($id);
        $assignment->delete();
        
        return redirect()->route('admin.teacher-class-assignments.index')
            ->with('success', 'Assignment removed successfully.');
    }
}