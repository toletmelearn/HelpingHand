<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\LessonPlan;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;

class ProfessionalLessonPlanController extends Controller
{
    public function index(Request $request)
    {
        $query = LessonPlan::with(['class', 'subject', 'teacher']);
        
        // Apply filters
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }
        
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }
        
        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }
        
        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }
        
        if ($request->filled('plan_type')) {
            $query->where('plan_type', $request->plan_type);
        }
        
        if ($request->filled('parent_visible')) {
            $query->where('show_to_parents', $request->parent_visible);
        }
        
        $lessonPlans = $query->latest()->paginate(20);
        
        // Get filter options
        $classes = SchoolClass::where('is_active', true)->orderBy('name')->get();
        $subjects = Subject::where('status', 'active')->orderBy('name')->get();
        $teachers = Teacher::where('status', 'active')->orderBy('name')->get();
        
        return view('admin.lesson-plans.professional-index', compact('lessonPlans', 'classes', 'subjects', 'teachers'));
    }

    public function show(LessonPlan $lessonPlan)
    {
        $lessonPlan->load(['class', 'subject', 'teacher']);
        
        return view('admin.lesson-plans.professional-show', compact('lessonPlan'));
    }

    public function destroy(LessonPlan $lessonPlan)
    {
        $lessonPlan->delete();
        
        return redirect()->route('admin.professional-lesson-plans.index')
            ->with('success', 'Lesson plan deleted successfully!');
    }
}
