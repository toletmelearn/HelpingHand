<?php

namespace App\Policies;

use App\Models\DailyTeachingWork;
use App\Models\User;
use App\Models\Teacher;
use Illuminate\Auth\Access\Response;

class DailyTeachingWorkPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher') || $user->hasRole('class-teacher') || $user->hasRole('student');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DailyTeachingWork $dailyTeachingWork): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('teacher')) {
            return $user->teacher && (
                $dailyTeachingWork->teacher_id === $user->teacher->id ||
                $dailyTeachingWork->class_name === $user->teacher->assigned_class
            );
        }
        
        if ($user->hasRole('class-teacher')) {
            return $user->teacher && $dailyTeachingWork->class_name === $user->teacher->assigned_class;
        }
        
        if ($user->hasRole('student')) {
            return $user->student && (
                $dailyTeachingWork->class_name === $user->student->class &&
                $dailyTeachingWork->section === $user->student->section
            );
        }
        
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DailyTeachingWork $dailyTeachingWork): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('teacher')) {
            return $user->teacher && $dailyTeachingWork->teacher_id === $user->teacher->id;
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DailyTeachingWork $dailyTeachingWork): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('teacher')) {
            return $user->teacher && $dailyTeachingWork->teacher_id === $user->teacher->id;
        }
        
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DailyTeachingWork $dailyTeachingWork): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DailyTeachingWork $dailyTeachingWork): bool
    {
        return $user->hasRole('admin');
    }
    
    public function downloadAttachment(User $user, DailyTeachingWork $dailyTeachingWork): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('teacher')) {
            return $user->teacher && (
                $dailyTeachingWork->teacher_id === $user->teacher->id ||
                $dailyTeachingWork->class_name === $user->teacher->assigned_class
            );
        }
        
        if ($user->hasRole('class-teacher')) {
            return $user->teacher && $dailyTeachingWork->class_name === $user->teacher->assigned_class;
        }
        
        if ($user->hasRole('student')) {
            return $user->student && (
                $dailyTeachingWork->class_name === $user->student->class &&
                $dailyTeachingWork->section === $user->student->section
            );
        }
        
        return false;
    }
}
