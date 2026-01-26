<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ClassTeacherAssignment;
use Illuminate\Auth\Access\Response;

class ClassTeacherAssignmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'staff']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ClassTeacherAssignment $classTeacherAssignment): bool
    {
        return $user->hasRole(['admin', 'staff']) || 
               $user->id === $classTeacherAssignment->teacher_id;
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
    public function update(User $user, ClassTeacherAssignment $classTeacherAssignment): bool
    {
        return $user->hasRole(['admin', 'staff']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ClassTeacherAssignment $classTeacherAssignment): bool
    {
        return $user->hasRole(['admin', 'staff']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ClassTeacherAssignment $classTeacherAssignment): bool
    {
        return $user->hasRole(['admin', 'staff']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ClassTeacherAssignment $classTeacherAssignment): bool
    {
        return $user->hasRole(['admin', 'staff']);
    }
}