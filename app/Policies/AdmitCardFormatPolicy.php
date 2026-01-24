<?php

namespace App\Policies;

use App\Models\AdmitCardFormat;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AdmitCardFormatPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view-admit-card-formats') || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AdmitCardFormat $admitCardFormat): bool
    {
        return $user->hasPermission('view-admit-card-formats') || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create-admit-card-formats') || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AdmitCardFormat $admitCardFormat): bool
    {
        return $user->hasPermission('edit-admit-card-formats') || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AdmitCardFormat $admitCardFormat): bool
    {
        return $user->hasPermission('delete-admit-card-formats') || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AdmitCardFormat $admitCardFormat): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AdmitCardFormat $admitCardFormat): bool
    {
        return false;
    }
}
