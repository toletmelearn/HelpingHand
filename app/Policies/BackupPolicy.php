<?php

namespace App\Policies;

use App\Models\Backup;
use App\Models\User;

class BackupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Backup $backup): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Backup $backup): bool
    {
        return $user->hasRole('admin');
    }

    public function download(User $user, Backup $backup): bool
    {
        return $user->hasRole('admin');
    }
}
