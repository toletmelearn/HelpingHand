<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;

class CertificatePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->hasPermission('view-certificates')
            || $user->hasPermission('manage-certificates');
    }

    /**
     * Determine whether the user can view the model (also used for
     * preview() and downloadPdf() -- both read-only rendering actions).
     */
    public function view(User $user, Certificate $certificate): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('manage-certificates');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Certificate $certificate): bool
    {
        return $this->create($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Certificate $certificate): bool
    {
        return $this->create($user);
    }

    /**
     * Determine whether the user can approve the certificate.
     */
    public function approve(User $user, Certificate $certificate): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can publish the certificate -- for a TC,
     * this is the actual leaving-school event (drops future dues, marks
     * the student tc_issued). Admin-only: narrowest sensible, matching the
     * plan's specific call-out of publish/revoke as the actions needing
     * protection most.
     */
    public function publish(User $user, Certificate $certificate): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can lock the certificate.
     */
    public function lock(User $user, Certificate $certificate): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can revoke the certificate.
     */
    public function revoke(User $user, Certificate $certificate): bool
    {
        return $user->hasRole('admin');
    }
}
