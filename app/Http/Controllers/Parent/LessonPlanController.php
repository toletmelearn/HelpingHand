<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LessonPlan;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;

class LessonPlanController extends Controller
{
    public function index()
    {
        $children = Student::where('parent_id', Auth::id())->get();
        $lessonPlans = collect();
        
        foreach ($children as $child) {
            $childPlans = LessonPlan::where('class_id', $child->class_id)
                ->where('section_id', $child->section_id)
                ->where('date', '>=', now()->startOfWeek())
                ->with(['teacher', 'class', 'section', 'subject'])
                ->get();
            $lessonPlans = $lessonPlans->merge($childPlans);
        }
        
        $lessonPlans = $lessonPlans->sortBy('date');
        
        return view('parent.lesson-plans.index', compact('lessonPlans', 'children'));
    }

    public function show(LessonPlan $lessonPlan)
    {
        $children = Student::where('parent_id', Auth::id())->get();
        $childInClass = $children->contains(function ($child) use ($lessonPlan) {
            return $child->class_id == $lessonPlan->class_id && $child->section_id == $lessonPlan->section_id;
        });
        
        if (!$childInClass) {
            abort(403);
        }
        
        $lessonPlan->load(['teacher', 'class', 'section', 'subject', 'createdBy']);
        return view('parent.lesson-plans.show', compact('lessonPlan'));
    }

    public function booksToSend()
    {
        $children = Student::where('parent_id', Auth::id())->get();
        $booksToSend = collect();
        
        foreach ($children as $child) {
            $tomorrowPlans = LessonPlan::where('class_id', $child->class_id)
                ->where('section_id', $child->section_id)
                ->where('date', now()->addDay()->toDateString())
                ->get();
            
            foreach ($tomorrowPlans as $plan) {
                if (!empty($plan->books_notebooks_required)) {
                    $booksToSend->push([
                        'child' => $child,
                        'subject' => $plan->subject->name,
                        'books' => $plan->books_notebooks_required,
                        'date' => $plan->date,
                    ]);
                }
            }
        }
        
        return view('parent.lesson-plans.books-to-send', compact('booksToSend'));
    }

    public function weeklyOverview()
    {
        $children = Student::where('parent_id', Auth::id())->get();
        $weeklyPlans = collect();
        
        foreach ($children as $child) {
            $childPlans = LessonPlan::where('class_id', $child->class_id)
                ->where('section_id', $child->section_id)
                ->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])
                ->with(['subject'])
                ->get();
            $weeklyPlans = $weeklyPlans->merge($childPlans);
        }
        
        $weeklyPlans = $weeklyPlans->groupBy('subject.name');
        
        return view('parent.lesson-plans.weekly-overview', compact('weeklyPlans', 'children'));
    }
}