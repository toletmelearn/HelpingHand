<?php

namespace App\Policies;

use App\Models\TeacherClassSubjectAssignment;
use App\Models\User;

class TeacherClassSubjectAssignmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->hasPermission('view-teacher-subject-assignment')
            || $user->hasPermission('manage-teacher-subject-assignment');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TeacherClassSubjectAssignment $assignment): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('manage-teacher-subject-assignment');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TeacherClassSubjectAssignment $assignment): bool
    {
        return $this->create($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TeacherClassSubjectAssignment $assignment): bool
    {
        return $this->create($user);
    }
}
