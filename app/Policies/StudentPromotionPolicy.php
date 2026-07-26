<?php

namespace App\Policies;

use App\Models\User;

class StudentPromotionPolicy
{
    /**
     * Determine whether the user can view promotion data (class lists,
     * destination options, a student's promotion history).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->hasPermission('view-student-promotion')
            || $user->hasPermission('manage-student-promotion');
    }

    /**
     * Determine whether the user can promote/pass-out students.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('manage-student-promotion');
    }
}
