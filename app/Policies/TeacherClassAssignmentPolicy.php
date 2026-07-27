<?php

namespace App\Policies;

use App\Models\TeacherClassAssignment;
use App\Models\User;

class TeacherClassAssignmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('staff');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TeacherClassAssignment $teacherClassAssignment): bool
    {
        return $user->hasRole('admin') || $user->hasRole('staff');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TeacherClassAssignment $teacherClassAssignment): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TeacherClassAssignment $teacherClassAssignment): bool
    {
        return $user->hasRole('admin');
    }
}
