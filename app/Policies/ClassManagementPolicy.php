<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ClassManagement;
use Illuminate\Auth\Access\Response;

class ClassManagementPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'staff', 'teacher']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ClassManagement $classManagement): bool
    {
        return $user->hasRole(['admin', 'staff', 'teacher']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'staff']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ClassManagement $classManagement): bool
    {
        return $user->hasRole(['admin', 'staff']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ClassManagement $classManagement): bool
    {
        return $user->hasRole(['admin', 'staff']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ClassManagement $classManagement): bool
    {
        return $user->hasRole(['admin', 'staff']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ClassManagement $classManagement): bool
    {
        return $user->hasRole(['admin', 'staff']);
    }
}