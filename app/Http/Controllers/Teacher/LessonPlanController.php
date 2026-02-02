<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LessonPlan;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth;

class LessonPlanController extends Controller
{
    public function index()
    {
        $lessonPlans = LessonPlan::where('teacher_id', Auth::id())
            ->with(['class', 'section', 'subject'])
            ->latest()
            ->paginate(20);
        
        return view('teacher.lesson-plans.index', compact('lessonPlans'));
    }

    public function create()
    {
        $classes = SchoolClass::all();
        $sections = Section::all();
        $subjects = Subject::all();
        
        return view('teacher.lesson-plans.create', compact('classes', 'sections', 'subjects'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'date' => 'required|date',
            'topic' => 'required|string|max:255',
            'learning_objectives' => 'required|string',
            'teaching_method' => 'nullable|string',
            'homework_classwork' => 'required|string',
            'books_notebooks_required' => 'required|string',
            'submission_assessment_notes' => 'nullable|string',
            'plan_type' => 'required|in:daily,weekly,monthly',
        ]);

        LessonPlan::create([
            'teacher_id' => Auth::id(),
            'class_id' => $request->class_id,
            'section_id' => $request->section_id,
            'subject_id' => $request->subject_id,
            'date' => $request->date,
            'topic' => $request->topic,
            'learning_objectives' => $request->learning_objectives,
            'teaching_method' => $request->teaching_method,
            'homework_classwork' => $request->homework_classwork,
            'books_notebooks_required' => $request->books_notebooks_required,
            'submission_assessment_notes' => $request->submission_assessment_notes,
            'plan_type' => $request->plan_type,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('teacher.lesson-plans.index')
            ->with('success', 'Lesson plan created successfully!');
    }

    public function show(LessonPlan $lessonPlan)
    {
        if ($lessonPlan->teacher_id !== Auth::id()) {
            abort(403);
        }
        
        $lessonPlan->load(['class', 'section', 'subject', 'createdBy', 'modifiedBy']);
        return view('teacher.lesson-plans.show', compact('lessonPlan'));
    }

    public function edit(LessonPlan $lessonPlan)
    {
        if ($lessonPlan->teacher_id !== Auth::id()) {
            abort(403);
        }
        
        $classes = SchoolClass::all();
        $sections = Section::all();
        $subjects = Subject::all();
        
        return view('teacher.lesson-plans.edit', compact('lessonPlan', 'classes', 'sections', 'subjects'));
    }

    public function update(Request $request, LessonPlan $lessonPlan)
    {
        if ($lessonPlan->teacher_id !== Auth::id()) {
            abort(403);
        }
        
        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'date' => 'required|date',
            'topic' => 'required|string|max:255',
            'learning_objectives' => 'required|string',
            'teaching_method' => 'nullable|string',
            'homework_classwork' => 'required|string',
            'books_notebooks_required' => 'required|string',
            'submission_assessment_notes' => 'nullable|string',
            'plan_type' => 'required|in:daily,weekly,monthly',
        ]);

        $lessonPlan->update([
            'class_id' => $request->class_id,
            'section_id' => $request->section_id,
            'subject_id' => $request->subject_id,
            'date' => $request->date,
            'topic' => $request->topic,
            'learning_objectives' => $request->learning_objectives,
            'teaching_method' => $request->teaching_method,
            'homework_classwork' => $request->homework_classwork,
            'books_notebooks_required' => $request->books_notebooks_required,
            'submission_assessment_notes' => $request->submission_assessment_notes,
            'plan_type' => $request->plan_type,
            'modified_by' => Auth::id(),
        ]);

        return redirect()->route('teacher.lesson-plans.index')
            ->with('success', 'Lesson plan updated successfully!');
    }

    public function destroy(LessonPlan $lessonPlan)
    {
        if ($lessonPlan->teacher_id !== Auth::id()) {
            abort(403);
        }
        
        $lessonPlan->delete();
        return redirect()->route('teacher.lesson-plans.index')
            ->with('success', 'Lesson plan deleted successfully!');
    }

    public function history()
    {
        $lessonPlans = LessonPlan::where('teacher_id', Auth::id())
            ->with(['class', 'section', 'subject'])
            ->latest()
            ->paginate(20);
        
        return view('teacher.lesson-plans.history', compact('lessonPlans'));
    }
}