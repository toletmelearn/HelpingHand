<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Teacher;
use App\Models\SchoolClass;
use App\Models\Subject;

class TeacherClassSubjectAssignmentController extends Controller
{
    public function index()
    {
        $teachers = Teacher::all();
        $classes = SchoolClass::all();
        $subjects = Subject::all();
        
        return view('admin.assign-teacher-class-subject.index', compact('teachers', 'classes', 'subjects'));
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
        ]);
        
        // Check if assignment already exists
        $existingAssignment = DB::table('teacher_class_subject_assignments')
            ->where('teacher_id', $request->teacher_id)
            ->where('class_id', $request->class_id)
            ->where('subject_id', $request->subject_id)
            ->first();
            
        if ($existingAssignment) {
            return redirect()->back()->withErrors(['error' => 'This teacher is already assigned to this class and subject.']);
        }
        
        // Create the assignment
        DB::table('teacher_class_subject_assignments')->insert([
            'teacher_id' => $request->teacher_id,
            'class_id' => $request->class_id,
            'subject_id' => $request->subject_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return redirect()->route('admin.assign-teacher-class-subject.index')
            ->with('success', 'Teacher successfully assigned to class and subject!');
    }
}