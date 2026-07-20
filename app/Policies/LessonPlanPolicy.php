<?php

namespace App\Policies;

use App\Models\LessonPlan;
use App\Models\User;
use App\Models\Student;
use Illuminate\Auth\Access\HandlesAuthorization;

class LessonPlanPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if a parent can view this lesson plan.
     */
    public function viewAsParent($user, LessonPlan $lessonPlan): bool
    {
        // When using authorize() method, the first parameter is the authenticated user
        // In parent guard context, this is the Student model
        if ($user instanceof Student) {
            return $lessonPlan->show_to_parents === 1 
                && $lessonPlan->class_id === $user->school_class_id;
        }
        
        // Check if authenticated via parent guard (fallback)
        if (auth()->guard('parent')->check()) {
            $student = auth()->guard('parent')->user();
            
            return $lessonPlan->show_to_parents === 1 
                && $lessonPlan->class_id === $student->school_class_id;
        }
        
        return false;
    }
    
    /**
     * Determine if a user can view any lesson plans.
     */
    public function viewAny($user): bool
    {
        return true; // Parents can view index
    }
    
    /**
     * Determine if a user can view the lesson plan.
     */
    public function view($user, LessonPlan $lessonPlan): bool
    {
        // For parent guard - the user here is actually the Student model
        if (auth()->guard('parent')->check()) {
            return $this->viewAsParent($user, $lessonPlan);
        }
        
        // Admin and teachers can view everything (if we get a regular user)
        if ($user instanceof \App\Models\User) {
            // Check if user has admin or teacher roles
            if ($user->hasRole('admin') || $user->hasRole('teacher')) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Additional method to check if lesson plan has parent-visible content.
     */
    public function hasParentVisibleContent(LessonPlan $lessonPlan): bool
    {
        return !empty($lessonPlan->parent_visible_content) 
            || !empty($lessonPlan->learning_objectives)
            || !empty($lessonPlan->activities);
    }
}