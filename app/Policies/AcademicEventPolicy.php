<?php

namespace App\Policies;

use App\Models\AcademicEvent;
use App\Models\User;

class AcademicEventPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasAnyRole(['clerk', 'accountant', 'staff', 'teacher']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AcademicEvent $academicEvent): bool
    {
        return $user->hasRole('admin') || $user->hasAnyRole(['clerk', 'accountant', 'staff', 'teacher']);
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
    public function update(User $user, AcademicEvent $academicEvent): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AcademicEvent $academicEvent): bool
    {
        return $user->hasRole('admin');
    }
}
