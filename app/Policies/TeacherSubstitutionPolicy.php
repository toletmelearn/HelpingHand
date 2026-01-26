<?php

namespace App\Policies;

use App\Models\TeacherSubstitution;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TeacherSubstitutionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TeacherSubstitution $teacherSubstitution): bool
    {
        return $user->hasRole('admin');
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
    public function update(User $user, TeacherSubstitution $teacherSubstitution): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TeacherSubstitution $teacherSubstitution): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can assign substitutes.
     */
    public function assignSubstitute(User $user, TeacherSubstitution $teacherSubstitution): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can approve substitutes.
     */
    public function approveSubstitute(User $user, TeacherSubstitution $teacherSubstitution): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can cancel substitutes.
     */
    public function cancelSubstitute(User $user, TeacherSubstitution $teacherSubstitution): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view today's substitutions.
     */
    public function viewTodaySubstitutions(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view absence overview.
     */
    public function viewAbsenceOverview(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can manage substitution rules.
     */
    public function manageRules(User $user): bool
    {
        return $user->hasRole('admin');
    }
}