<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\LessonPlan;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LessonPlanController extends Controller
{
    public function index()
    {
        $parent = auth('parent')->user();

        if (!$parent) {
            abort(403, 'Parent not logged in');
        }

        if (!$parent->student_id) {
            return view('parent.lesson-plans.index', [
                'lessonPlans' => collect()
            ]);
        }

        $student = Student::find($parent->student_id);

        if (!$student) {
            return view('parent.lesson-plans.index', [
                'lessonPlans' => collect()
            ]);
        }

        $lessonPlans = \App\Models\LessonPlan::where('class_id', $student->school_class_id)
            ->where('show_to_parents', 1)
            ->latest()
            ->get();

        \Illuminate\Support\Facades\Log::info('Parent viewing lesson plans', [
            'parent_id' => $parent->id ?? null,
            'student_id' => $student->id ?? null,
            'class_id' => $student->school_class_id ?? null,
            'plans_count' => $lessonPlans->count()
        ]);

        return view('parent.lesson-plans.index', [
            'lessonPlans' => $lessonPlans ?? collect()
        ]);
    }
    
    public function show($id)
    {
        $parent = Auth::guard('parent')->user();

        if (!$parent) {
            abort(403, 'Parent not logged in');
        }

        $student = $parent->student;

        if (!$student) {
            abort(403, 'Student not linked to parent');
        }

        $lessonPlan = \App\Models\LessonPlan::findOrFail($id);

        // SECURITY CHECK
        if ($lessonPlan->class_id != $student->school_class_id) {
            abort(403, 'Unauthorized access');
        }

        if (!$lessonPlan->show_to_parents) {
            abort(403, 'Not visible to parents');
        }

        return view('parent.lesson-plans.show', compact('lessonPlan'));
    }
    
    /**
     * Show books and notebooks required for upcoming lessons.
     */
    public function booksToSend()
    {
        $parent = Auth::guard('parent')->user();
        
        if (!$parent || !$parent->student) {
            return redirect()->back()->with('error', 'Student not linked to parent');
        }
        
        $student = $parent->student;
        
        // Get lesson plans with books/notebooks required for student's class
        $lessonPlans = LessonPlan::where('class_id', $student->school_class_id)
            ->where('show_to_parents', 1)
            ->whereNotNull('books_notebooks_required')
            ->where('books_notebooks_required', '!=', '')
            ->orderBy('date', 'asc')
            ->get();
        
        return view('parent.lesson-plans.books-to-send', compact('lessonPlans', 'student'));
    }
    
    /**
     * Show weekly overview of lesson plans.
     */
    public function weeklyOverview()
    {
        $parent = Auth::guard('parent')->user();
        
        if (!$parent || !$parent->student) {
            return redirect()->back()->with('error', 'Student not linked to parent');
        }
        
        $student = $parent->student;
        
        // Get current week's lesson plans
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();
        
        $lessonPlans = LessonPlan::where('class_id', $student->school_class_id)
            ->where('show_to_parents', 1)
            ->whereBetween('date', [$startOfWeek, $endOfWeek])
            ->orderBy('date', 'asc')
            ->get();
        
        return view('parent.lesson-plans.weekly-overview', compact('lessonPlans', 'student', 'startOfWeek', 'endOfWeek'));
    }
    
    /**
     * Filter lesson plan content for parents.
     */
    private function filterParentVisibleContent(LessonPlan $lessonPlan): array
    {
        // Define fields that are safe for parents to see
        $parentVisibleFields = [
            'id',
            'title',
            'date',
            'topic',
            'learning_objectives',
            'homework_classwork',
            'materials',
            'activities',
            'assessment',
            'parent_visible_content',
            'teacher' => ['name'], // Only teacher name
            'subject' => ['name'],  // Only subject name
            'class' => ['name'],    // Only class name
            'duration',
            'plan_type'
        ];
        
        // Return only filtered data
        return collect($lessonPlan->toArray())
            ->only(array_keys($parentVisibleFields))
            ->toArray();
    }
}