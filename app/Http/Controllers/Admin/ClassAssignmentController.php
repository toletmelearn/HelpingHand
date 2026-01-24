<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassManagement;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\Request;

class ClassAssignmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show assignment screen for Class → Sections
     */
    public function assignSections(ClassManagement $class)
    {
        $this->authorize('update', $class);
        $sections = Section::all();
        return view('admin.class-assignments.assign-sections', compact('class', 'sections'));
    }

    /**
     * Assign sections to a class
     */
    public function saveSectionAssignment(Request $request, ClassManagement $class)
    {
        $this->authorize('update', $class);
        
        $request->validate([
            'sections' => 'array',
            'sections.*' => 'exists:sections,id'
        ]);

        // Clear existing assignments and assign new ones
        $class->sections()->sync($request->sections ?? []);

        return redirect()->route('admin.classes.assign-sections', ['class' => $class->id])
                         ->with('success', 'Sections assigned successfully.');
    }

    /**
     * Show assignment screen for Class → Subjects
     */
    public function assignSubjects(ClassManagement $class)
    {
        $this->authorize('update', $class);
        $subjects = Subject::all();
        $assignedSubjects = $class->subjects()->pluck('subject_id')->toArray();
        return view('admin.class-assignments.assign-subjects', compact('class', 'subjects', 'assignedSubjects'));
    }

    /**
     * Assign subjects to a class
     */
    public function saveSubjectAssignment(Request $request, ClassManagement $class)
    {
        $this->authorize('update', $class);
        
        $request->validate([
            'subjects' => 'array',
            'subjects.*' => 'exists:subjects,id'
        ]);

        // Clear existing assignments and assign new ones
        $class->subjects()->sync($request->subjects ?? []);

        return redirect()->route('admin.classes.assign-subjects', ['class' => $class->id])
                         ->with('success', 'Subjects assigned successfully.');
    }

    /**
     * Show assignment screen for Subject → Teacher
     */
    public function assignSubjectTeachers()
    {
        $subjects = Subject::with('teachers')->get();
        $teachers = Teacher::all();
        return view('admin.class-assignments.assign-subject-teachers', compact('subjects', 'teachers'));
    }

    /**
     * Assign teachers to subjects
     */
    public function saveSubjectTeacherAssignment(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'teacher_ids' => 'array',
            'teacher_ids.*' => 'exists:teachers,id'
        ]);

        $subject = Subject::find($request->subject_id);
        $this->authorize('update', $subject);

        // Sync teacher assignments for this subject
        $syncData = [];
        foreach ($request->teacher_ids as $teacherId) {
            $syncData[$teacherId] = [
                'class_id' => $request->class_id ?? null,
                'assigned_at' => now(),
                'is_primary' => $request->primary_teacher_id == $teacherId
            ];
        }
        
        $subject->teachers()->sync($syncData);

        return redirect()->route('admin.classes.assign-subject-teachers')
                         ->with('success', 'Teachers assigned to subject successfully.');
    }

    /**
     * Show assignment screen for Class → Class Teacher
     */
    public function assignClassTeacher(ClassManagement $class)
    {
        $this->authorize('update', $class);
        $teachers = Teacher::all();
        $currentTeacher = $class->teachers()->first();
        return view('admin.class-assignments.assign-class-teacher', compact('class', 'teachers', 'currentTeacher'));
    }

    /**
     * Assign class teacher to a class
     */
    public function saveClassTeacherAssignment(Request $request, ClassManagement $class)
    {
        $this->authorize('update', $class);
        
        $request->validate([
            'teacher_id' => 'required|exists:teachers,id'
        ]);

        // Sync the class teacher assignment
        $class->teachers()->sync([$request->teacher_id]);

        return redirect()->route('admin.classes.assign-class-teacher', ['class' => $class->id])
                         ->with('success', 'Class teacher assigned successfully.');
    }
}