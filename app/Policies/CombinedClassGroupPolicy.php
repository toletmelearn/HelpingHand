<?php

namespace App\Policies;

use App\Models\CombinedClassGroup;
use App\Models\User;

class CombinedClassGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    public function view(User $user, CombinedClassGroup $group): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    public function delete(User $user, CombinedClassGroup $group): bool
    {
        return $user->hasRole('admin');
    }
}
