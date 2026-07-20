<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Result;

class ResultPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('view-results');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Result $result): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('view-results');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('create-results');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Result $result): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('edit-results');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Result $result): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('delete-results');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Result $result): bool
    {
        return $user->hasRole('admin');
    }
}