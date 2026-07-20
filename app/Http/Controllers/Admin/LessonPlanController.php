<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LessonPlan;
use App\Models\Teacher;
use App\Models\SchoolClass;
use Illuminate\Http\Request;

class LessonPlanController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', LessonPlan::class);

        // Get filter options
        $teachers = Teacher::all();
        $classes = SchoolClass::all();

        // Apply filters dynamically - show all if no filters selected
        $query = LessonPlan::with(['teacher', 'class', 'subject']);

        if ($request->teacher_id) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->class_id) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->date) {
            $query->whereDate('date', $request->date);
        }

        $lessonPlans = $query->latest()->get();

        // Only show summary if at least one filter is applied
        $hasFilters = $request->filled(['teacher_id', 'class_id', 'date']);
        
        if ($hasFilters) {
            $summary = [
                'total_plans' => $lessonPlans->count(),
                'teacher_name' => $request->teacher_id ? Teacher::find($request->teacher_id)?->name : 'All Teachers',
                'class_name' => $request->class_id ? SchoolClass::find($request->class_id)?->name : 'All Classes',
                'date' => $request->date ?: 'All Dates',
            ];
        } else {
            $summary = null;
        }

        return view('admin.lesson-plans.index', compact('lessonPlans', 'teachers', 'classes', 'summary'));
    }
    
    public function show(LessonPlan $lessonPlan)
    {
        $this->authorize('view', $lessonPlan);
        
        $lessonPlan->load(['teacher', 'class', 'subject']);
        
        return view('admin.lesson-plans.show', compact('lessonPlan'));
    }
}