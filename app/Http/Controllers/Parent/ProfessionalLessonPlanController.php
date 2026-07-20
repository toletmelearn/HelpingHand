<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\LessonPlan;
use App\Models\Student;

class ProfessionalLessonPlanController extends Controller
{
    public function index()
    {
        $parent = Auth::guard('parent')->user();
        
        if (!$parent || !$parent->student) {
            return redirect()->back()->with('error', 'Student not linked to parent');
        }
        
        $student = $parent->student;
        
        // Get lesson plans for the student's class that are visible to parents
        $plans = LessonPlan::where('class_id', $student->school_class_id)
            ->where('show_to_parents', 1)
            ->with(['teacher', 'subject', 'class'])
            ->latest()
            ->paginate(15);

        return view('parent.lesson-plans.professional-index', compact('plans'));
    }
    
    public function show(LessonPlan $lessonPlan)
    {
        $parent = Auth::guard('parent')->user();
        
        if (!$parent || !$parent->student) {
            abort(403, 'Student not linked to parent');
        }
        
        $student = $parent->student;
        
        // Check if this lesson plan belongs to the student's class and is visible to parents
        if ($lessonPlan->class_id != $student->school_class_id || !$lessonPlan->show_to_parents) {
            abort(403, 'Unauthorized access to this lesson plan.');
        }
        
        // Load necessary relationships
        $lessonPlan->load(['teacher', 'subject', 'class']);
        
        return view('parent.lesson-plans.professional-show', compact('lessonPlan'));
    }
}
