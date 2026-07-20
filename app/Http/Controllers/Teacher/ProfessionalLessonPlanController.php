<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\LessonPlan;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherClassSubjectAssignment;

class ProfessionalLessonPlanController extends Controller
{
    public function index()
    {
        $teacherLogin = Auth::guard('teacher')->user();
        if (!$teacherLogin) {
            return redirect()->back()->with('error','Teacher not logged in. Please login again.');
        }
        
        // Map to actual teacher record using teacher_id
        $teacher = $teacherLogin->teacher;
        
        if(!$teacher){
            return redirect()->back()->with('error','Teacher record not found. Contact admin.');
        }
        
        $lessonPlans = LessonPlan::where('teacher_id', $teacher->id)
            ->with(['class', 'subject'])
            ->latest()
            ->paginate(15);

        return view('teacher.lesson-plans.professional-index', compact('lessonPlans', 'teacher'));
    }

    public function create()
    {
        $teacherLogin = Auth::guard('teacher')->user();
        if (!$teacherLogin) {
            return redirect()->back()->with('error','Teacher not logged in. Please login again.');
        }
        
        // Map to actual teacher record using teacher_id
        $teacher = $teacherLogin->teacher;
        
        if(!$teacher){
            return redirect()->back()->with('error','Teacher record not found. Contact admin.');
        }
        
        // Get classes assigned to this teacher
        $assignments = TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
            ->with(['schoolClass', 'subject'])
            ->get();
            
        $classes = $assignments->pluck('schoolClass')->unique('id')->values();
        $subjects = $assignments->pluck('subject')->unique('id')->values();
        
        if ($classes->isEmpty()) {
            return redirect()->back()->with('error', 'You have not been assigned to any classes yet. Please contact admin.');
        }

        return view('teacher.lesson-plans.professional-create', compact('classes', 'subjects', 'teacher'));
    }

    public function store(Request $request)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        if (!$teacherLogin) {
            return redirect()->back()->with('error','Teacher not logged in. Please login again.');
        }
        
        // Map to actual teacher record using teacher_id
        $teacher = $teacherLogin->teacher;
        
        if(!$teacher){
            return redirect()->back()->with('error','Teacher record not found. Contact admin.');
        }
        
        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'date' => 'required|date',
            'title' => 'required|string|max:255',
            'full_content' => 'required|string',
            'parent_visible_content' => 'nullable|string',
            'visible_to_parent' => 'boolean',
            'topic' => 'nullable|string|max:255',
            'learning_objectives' => 'nullable|string',
            'teaching_method' => 'nullable|string',
            'homework_classwork' => 'nullable|string',
            'books_notebooks_required' => 'nullable|string',
            'submission_assessment_notes' => 'nullable|string',
            'plan_type' => 'required|in:daily,weekly,monthly',
            'duration' => 'nullable|integer|min:1',
        ]);

        $lessonPlan = new LessonPlan();
        $lessonPlan->teacher_id = $teacher->id;
        $lessonPlan->class_id = $request->class_id;
        $lessonPlan->subject_id = $request->subject_id;
        $lessonPlan->date = $request->date;
        $lessonPlan->title = $request->title;
        $lessonPlan->topic = $request->topic;
        $lessonPlan->full_content = $request->full_content;
        $lessonPlan->parent_visible_content = $request->parent_visible_content;
        $lessonPlan->show_to_parents = $request->has('visible_to_parent') ? 1 : 0;
        $lessonPlan->learning_objectives = $request->learning_objectives;
        $lessonPlan->teaching_method = $request->teaching_method;
        $lessonPlan->homework_classwork = $request->homework_classwork;
        $lessonPlan->books_notebooks_required = $request->books_notebooks_required;
        $lessonPlan->submission_assessment_notes = $request->submission_assessment_notes;
        $lessonPlan->plan_type = $request->plan_type;
        $lessonPlan->duration = $request->duration;
        $lessonPlan->created_by = $teacher->id; // Use teacher business ID, not login ID
        $lessonPlan->save();

        return redirect()->route('teacher.professional-lesson-plans.index')
            ->with('success', 'Lesson plan created successfully!');
    }

    public function show(LessonPlan $lessonPlan)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        if (!$teacherLogin) {
            return redirect()->back()->with('error','Teacher not logged in. Please login again.');
        }
        
        // Map to actual teacher record using teacher_id
        $teacher = $teacherLogin->teacher;
        
        if(!$teacher){
            return redirect()->back()->with('error','Teacher record not found. Contact admin.');
        }
        
        // Check if this lesson plan belongs to the teacher
        if ($lessonPlan->teacher_id != $teacher->id) {
            return redirect()->back()->with('error', 'You do not have permission to view this lesson plan.');
        }
        
        $lessonPlan->load(['class', 'subject', 'teacher']);
        
        return view('teacher.lesson-plans.professional-show', compact('lessonPlan'));
    }

    public function edit(LessonPlan $lessonPlan)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        if (!$teacherLogin) {
            return redirect()->back()->with('error','Teacher not logged in. Please login again.');
        }
        
        // Map to actual teacher record using teacher_id
        $teacher = $teacherLogin->teacher;
        
        if(!$teacher){
            return redirect()->back()->with('error','Teacher record not found. Contact admin.');
        }
        
        // Check if this lesson plan belongs to the teacher
        if ($lessonPlan->teacher_id != $teacher->id) {
            return redirect()->back()->with('error', 'You do not have permission to edit this lesson plan.');
        }
        
        // Get classes assigned to this teacher
        $assignments = TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
            ->with(['schoolClass', 'subject'])
            ->get();
            
        $classes = $assignments->pluck('schoolClass')->unique('id')->values();
        $subjects = $assignments->pluck('subject')->unique('id')->values();
        
        $lessonPlan->load(['class', 'subject']);
        
        return view('teacher.lesson-plans.professional-edit', compact('lessonPlan', 'classes', 'subjects'));
    }

    public function update(Request $request, LessonPlan $lessonPlan)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        if (!$teacherLogin) {
            return redirect()->back()->with('error','Teacher not logged in. Please login again.');
        }
        
        // Map to actual teacher record using teacher_id
        $teacher = $teacherLogin->teacher;
        
        if(!$teacher){
            return redirect()->back()->with('error','Teacher record not found. Contact admin.');
        }
        
        // Check if this lesson plan belongs to the teacher
        if ($lessonPlan->teacher_id != $teacher->id) {
            return redirect()->back()->with('error', 'You do not have permission to update this lesson plan.');
        }
        
        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'date' => 'required|date',
            'title' => 'required|string|max:255',
            'full_content' => 'required|string',
            'parent_visible_content' => 'nullable|string',
            'visible_to_parent' => 'boolean',
            'topic' => 'nullable|string|max:255',
            'learning_objectives' => 'nullable|string',
            'teaching_method' => 'nullable|string',
            'homework_classwork' => 'nullable|string',
            'books_notebooks_required' => 'nullable|string',
            'submission_assessment_notes' => 'nullable|string',
            'plan_type' => 'required|in:daily,weekly,monthly',
            'duration' => 'nullable|integer|min:1',
        ]);

        $lessonPlan->class_id = $request->class_id;
        $lessonPlan->subject_id = $request->subject_id;
        $lessonPlan->date = $request->date;
        $lessonPlan->title = $request->title;
        $lessonPlan->topic = $request->topic;
        $lessonPlan->full_content = $request->full_content;
        $lessonPlan->parent_visible_content = $request->parent_visible_content;
        $lessonPlan->show_to_parents = $request->has('visible_to_parent') ? 1 : 0;
        $lessonPlan->learning_objectives = $request->learning_objectives;
        $lessonPlan->teaching_method = $request->teaching_method;
        $lessonPlan->homework_classwork = $request->homework_classwork;
        $lessonPlan->books_notebooks_required = $request->books_notebooks_required;
        $lessonPlan->submission_assessment_notes = $request->submission_assessment_notes;
        $lessonPlan->plan_type = $request->plan_type;
        $lessonPlan->duration = $request->duration;
        $lessonPlan->modified_by = $teacher->id; // Use teacher business ID, not login ID
        $lessonPlan->save();

        return redirect()->route('teacher.professional-lesson-plans.index')
            ->with('success', 'Lesson plan updated successfully!');
    }

    public function destroy(LessonPlan $lessonPlan)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        if (!$teacherLogin) {
            return redirect()->back()->with('error','Teacher not logged in. Please login again.');
        }
        
        // Map to actual teacher record using teacher_id
        $teacher = $teacherLogin->teacher;
        
        if(!$teacher){
            return redirect()->back()->with('error','Teacher record not found. Contact admin.');
        }
        
        // Check if this lesson plan belongs to the teacher
        if ($lessonPlan->teacher_id != $teacher->id) {
            return redirect()->back()->with('error', 'You do not have permission to delete this lesson plan.');
        }
        
        $lessonPlan->delete();
        
        return redirect()->route('teacher.professional-lesson-plans.index')
            ->with('success', 'Lesson plan deleted successfully!');
    }
}
