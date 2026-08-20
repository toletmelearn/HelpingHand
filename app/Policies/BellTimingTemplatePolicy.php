<?php

namespace App\Policies;

use App\Models\BellTimingTemplate;
use App\Models\User;

/**
 * Deliberately separate from BellTimingPolicy, which allows both `admin`
 * and `teacher` to manage individual Bell Timing records. Templates are an
 * administrative bulk-application tool -- every ability here is admin-only,
 * so a teacher retains their existing Bell Timing access without gaining
 * any template capability.
 */
class BellTimingTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, BellTimingTemplate $template): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, BellTimingTemplate $template): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, BellTimingTemplate $template): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Applying a template creates real BellTiming rows for one or more
     * classes -- the same authority level as delete, kept as its own
     * ability so it can be audited/logged distinctly from plain edits.
     */
    public function apply(User $user, BellTimingTemplate $template): bool
    {
        return $user->hasRole('admin');
    }
}
