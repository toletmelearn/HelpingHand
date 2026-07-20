<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Result;
use Illuminate\Auth\Access\Response;

class ResultEntryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher') || $user->hasPermission('view-results');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Result $result): bool
    {
        // Admin and users with view-results permission can view all results
        if ($user->hasRole('admin') || $user->hasPermission('view-results')) {
            return true;
        }
        
        // Teachers can only view results of their subject
        if ($user->hasRole('teacher') && $user->teacher) {
            return $result->subject === $user->teacher->subject_specialization;
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
    public function update(User $user, Result $result): bool
    {
        // Cannot update verified results
        if ($result->is_verified) {
            return false;
        }
        
        // Admin and users with edit-results permission can update all unverified results
        if ($user->hasRole('admin') || $user->hasPermission('edit-results')) {
            return true;
        }
        
        // Teachers can update results of their subject only
        if ($user->hasRole('teacher') && $user->teacher) {
            return $result->subject === $user->teacher->subject_specialization;
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Result $result): bool
    {
        // Cannot delete verified results
        if ($result->is_verified) {
            return false;
        }
        
        // Admin and users with delete-results permission can delete all unverified results
        if ($user->hasRole('admin') || $user->hasPermission('delete-results')) {
            return true;
        }
        
        // Teachers cannot delete results
        return false;
    }

    /**
     * Determine whether the user can verify the model.
     */
    public function verify(User $user, Result $result): bool
    {
        // Only admin and users with verify-results permission can verify results
        return $user->hasRole('admin') || $user->hasPermission('verify-results');
    }

    /**
     * Determine whether the user can bulk entry.
     */
    public function bulkEntry(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher') || $user->hasPermission('create-results');
    }
}