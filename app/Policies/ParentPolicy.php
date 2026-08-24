<?php

namespace App\Policies;

use App\Models\ParentModel;
use App\Models\User;

class ParentPolicy
{
    /**
     * Determine whether the user can view the parent directory/listing.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view a specific parent's profile.
     */
    public function view(User $user, ParentModel $parent): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can edit a parent's profile.
     */
    public function update(User $user, ParentModel $parent): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can reset a parent's password.
     */
    public function resetPassword(User $user, ParentModel $parent): bool
    {
        return $user->hasRole('admin');
    }
}
