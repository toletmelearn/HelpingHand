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
        return $user->hasRole('admin') || $user->hasPermission('view-teachers');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TeacherSubstitution $teacherSubstitution): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('view-teachers');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('manage-substitutions');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TeacherSubstitution $teacherSubstitution): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('manage-substitutions');
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
        return $user->hasRole('admin') || $user->hasPermission('manage-substitutions');
    }

    /**
     * Determine whether the user can approve substitutes.
     */
    public function approveSubstitute(User $user, TeacherSubstitution $teacherSubstitution): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('manage-substitutions');
    }

    /**
     * Determine whether the user can cancel substitutes.
     */
    public function cancelSubstitute(User $user, TeacherSubstitution $teacherSubstitution): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('manage-substitutions');
    }

    /**
     * T3 item 3: the "teacher absent today" flow -- view the day's slots
     * and ranked suggestions, and one-click assign. Same admin +
     * manage-substitutions gate as the rest of the write actions above.
     */
    public function manageAbsentToday(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('manage-substitutions');
    }

    /**
     * Determine whether the user can view today's substitutions.
     */
    public function viewTodaySubstitutions(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('view-teachers');
    }

    /**
     * Determine whether the user can view absence overview.
     */
    public function viewAbsenceOverview(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('view-teachers');
    }

    /**
     * Determine whether the user can manage substitution rules.
     */
    public function manageRules(User $user): bool
    {
        return $user->hasRole('admin');
    }
}