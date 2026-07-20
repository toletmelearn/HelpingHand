<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CBSEResult;
use Illuminate\Auth\Access\Response;

class CBSEResultPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher') || $user->hasRole('principal') || $user->hasPermission('view-results');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CBSEResult $result): bool
    {
        // Admin, principal, and users with view-results permission can view all results
        if ($user->hasRole('admin') || $user->hasRole('principal') || $user->hasPermission('view-results')) {
            return true;
        }
        
        // Teachers can only view results of students in their classes
        if ($user->hasRole('teacher')) {
            return $user->teacher && $user->teacher->classes()
                ->where('class_id', $result->student->class_id)
                ->exists();
        }
        
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher') || $user->hasPermission('create-results');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CBSEResult $result): bool
    {
        // Cannot update locked results
        if ($result->is_locked) {
            return false;
        }
        
        // Admin and users with edit-results permission can update all results
        if ($user->hasRole('admin') || $user->hasPermission('edit-results')) {
            return true;
        }
        
        // Teachers can update results of students in their classes
        if ($user->hasRole('teacher')) {
            return $user->teacher && $user->teacher->classes()
                ->where('class_id', $result->student->class_id)
                ->exists();
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CBSEResult $result): bool
    {
        // Cannot delete locked results
        if ($result->is_locked) {
            return false;
        }
        
        // Admin and users with delete-results permission can delete all results
        if ($user->hasRole('admin') || $user->hasPermission('delete-results')) {
            return true;
        }
        
        // Teachers cannot delete results
        return false;
    }

    /**
     * Determine whether the user can lock/unlock the model.
     */
    public function lock(User $user, CBSEResult $result): bool
    {
        // Only admin and principal can lock/unlock results
        return $user->hasRole('admin') || $user->hasRole('principal');
    }

    /**
     * Determine whether the user can bulk upload results.
     */
    public function bulkUpload(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher') || $user->hasPermission('create-results');
    }
}