<?php

namespace App\Policies;

use App\Models\User;
use App\Models\HomeworkNotice;
use Illuminate\Auth\Access\Response;

class HomeworkNoticePolicy
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
    public function view(User $user, HomeworkNotice $homeworkNotice): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('teacher')) {
            return $user->id == $homeworkNotice->assigned_by;
        }
        
        if ($user->hasRole('parent')) {
            // Parents can view homework for their children's class
            // This will be controlled in the controller based on student relationships
            return true;
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
    public function update(User $user, HomeworkNotice $homeworkNotice): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('teacher')) {
            return $user->id == $homeworkNotice->assigned_by;
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, HomeworkNotice $homeworkNotice): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('teacher')) {
            return $user->id == $homeworkNotice->assigned_by;
        }
        
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, HomeworkNotice $homeworkNotice): bool
    {
        return $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, HomeworkNotice $homeworkNotice): bool
    {
        return $user->hasRole(['admin']);
    }
}