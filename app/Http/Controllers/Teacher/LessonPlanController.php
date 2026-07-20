<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\LessonPlan;
use App\Models\SchoolClass;
use App\Models\Subject;

class LessonPlanController extends Controller
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
            ->with(['class', 'subject', 'section'])
            ->latest()
            ->paginate(10);

        return view('teacher.lesson-plans.index', compact('lessonPlans', 'teacher'));
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

        // Get teacher's assigned classes and subjects
        $assignments = DB::table('teacher_class_subject_assignments')
            ->where('teacher_class_subject_assignments.teacher_id', $teacher->id)
            ->join('school_classes', 'school_classes.id', '=', 'teacher_class_subject_assignments.class_id')
            ->join('subjects', 'subjects.id', '=', 'teacher_class_subject_assignments.subject_id')
            ->select(
                'school_classes.id as class_id',
                'school_classes.name as class_name',
                'subjects.id as subject_id',
                'subjects.name as subject_name'
            )
            ->get();

        $classes = $assignments->pluck('class_name', 'class_id');
        $subjects = $assignments->pluck('subject_name', 'subject_id');

        return view('teacher.lesson-plans.create', compact('classes', 'subjects', 'teacher'));
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

        // Validate and create lesson plan
        $validated = $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'title' => 'required|string|max:255',
            'plan_type' => 'required|in:daily,weekly,15days,monthly',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'full_content' => 'required|string',
            'parent_visible_content' => 'nullable|string',
            'show_to_parents' => 'nullable|boolean',
            // Optional fields that may be sent from enhanced forms
            'topic' => 'nullable|string|max:255',
            'learning_objectives' => 'nullable|string',
            'teaching_method' => 'nullable|string',
            'homework_classwork' => 'nullable|string',
            'books_notebooks_required' => 'nullable|string',
            'submission_assessment_notes' => 'nullable|string',
        ]);

        // Prepare the base data for the lesson plan
        $lessonPlanData = [
            'teacher_id' => $teacher->id,
            'class_id' => $validated['class_id'],
            'subject_id' => $validated['subject_id'],
            'title' => $validated['title'],
            'date' => $validated['start_date'], // Use start_date as the main date
            'plan_type' => $validated['plan_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'full_content' => $validated['full_content'],
            'parent_visible_content' => $validated['parent_visible_content'] ?? null,
            'show_to_parents' => $validated['show_to_parents'] ?? false,
            // Add optional fields that might exist in enhanced forms
            'topic' => $validated['topic'] ?? null,
            'learning_objectives' => $validated['learning_objectives'] ?? null,
            'teaching_method' => $validated['teaching_method'] ?? null,
            'homework_classwork' => $validated['homework_classwork'] ?? null,
            'books_notebooks_required' => $validated['books_notebooks_required'] ?? null,
            'submission_assessment_notes' => $validated['submission_assessment_notes'] ?? null,
        ];

        // Only add created_by if it's a valid user ID
        $teacherUserId = Auth::guard('teacher')->user()->id;
        // We'll skip setting created_by since teacher IDs are not user IDs in this system
        
        LessonPlan::create($lessonPlanData);
        
        Log::info('Lesson plan created', [
            'teacher_id' => $teacher->id,
            'class_id' => $validated['class_id'],
            'subject_id' => $validated['subject_id'],
            'title' => $validated['title'],
            'plan_type' => $validated['plan_type']
        ]);

        return redirect()->route('teacher.lesson-plans.index')
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

        // Ensure teacher can only view their own lesson plans
        if ($lessonPlan->teacher_id != $teacher->id) {
            return redirect()->back()->with('error', 'Unauthorized access to lesson plan.');
        }

        return view('teacher.lesson-plans.show', compact('lessonPlan', 'teacher'));
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

        // Ensure teacher can only edit their own lesson plans
        if ($lessonPlan->teacher_id != $teacher->id) {
            return redirect()->back()->with('error', 'Unauthorized access to lesson plan.');
        }

        // Get teacher's assigned classes and subjects
        $assignments = DB::table('teacher_class_subject_assignments')
            ->where('teacher_class_subject_assignments.teacher_id', $teacher->id)
            ->join('school_classes', 'school_classes.id', '=', 'teacher_class_subject_assignments.class_id')
            ->join('subjects', 'subjects.id', '=', 'teacher_class_subject_assignments.subject_id')
            ->select(
                'school_classes.id as class_id',
                'school_classes.name as class_name',
                'subjects.id as subject_id',
                'subjects.name as subject_name'
            )
            ->get();

        // Get actual model objects for the dropdowns
        $classes = \App\Models\SchoolClass::whereIn('id', $assignments->pluck('class_id'))->get();
        $subjects = \App\Models\Subject::whereIn('id', $assignments->pluck('subject_id'))->get();
        $sections = \App\Models\Section::all(); // Get all sections

        return view('teacher.lesson-plans.edit', compact('lessonPlan', 'classes', 'subjects', 'sections', 'teacher'));
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

        // Ensure teacher can only update their own lesson plans
        if ($lessonPlan->teacher_id != $teacher->id) {
            return redirect()->back()->with('error', 'Unauthorized access to lesson plan.');
        }

        // Validate and update lesson plan
        $validated = $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'topic' => 'required|string|max:255',
            'date' => 'required|date',
            'duration' => 'required|integer|min:1',
            'objectives' => 'required|string',
            'materials' => 'nullable|string',
            'activities' => 'required|string',
            'assessment' => 'nullable|string',
        ]);

        $lessonPlan->update([
            'class_id' => $validated['class_id'],
            'subject_id' => $validated['subject_id'],
            'topic' => $validated['topic'],
            'date' => $validated['date'],
            'duration' => $validated['duration'],
            'objectives' => $validated['objectives'],
            'materials' => $validated['materials'],
            'activities' => $validated['activities'],
            'assessment' => $validated['assessment'],
        ]);

        return redirect()->route('teacher.lesson-plans.index')
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

        // Ensure teacher can only delete their own lesson plans
        if ($lessonPlan->teacher_id != $teacher->id) {
            return redirect()->back()->with('error', 'Unauthorized access to lesson plan.');
        }

        $lessonPlan->delete();

        return redirect()->route('teacher.lesson-plans.index')
            ->with('success', 'Lesson plan deleted successfully!');
    }
}