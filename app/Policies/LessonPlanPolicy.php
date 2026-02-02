<?php

namespace App\Policies;

use App\Models\User;
use App\Models\LessonPlan;
use Illuminate\Auth\Access\Response;

class LessonPlanPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'teacher', 'parent']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LessonPlan $lessonPlan): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('teacher')) {
            return $user->id == $lessonPlan->teacher_id;
        }
        
        if ($user->hasRole('parent')) {
            // Allow parents to view lesson plans for their children's class/section
            return true; // This will be controlled in the controller based on student relationships
        }
        
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'teacher']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LessonPlan $lessonPlan): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('teacher')) {
            return $user->id == $lessonPlan->teacher_id;
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LessonPlan $lessonPlan): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('teacher')) {
            return $user->id == $lessonPlan->teacher_id;
        }
        
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LessonPlan $lessonPlan): bool
    {
        return $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LessonPlan $lessonPlan): bool
    {
        return $user->hasRole(['admin']);
    }
}