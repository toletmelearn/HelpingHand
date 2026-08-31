<?php

namespace App\Policies;

use App\Models\Datesheet;
use App\Models\User;

/**
 * Confirmed decision: no new "Exam Cell" role -- permission-based, exact
 * same pattern as TeacherClassSubjectAssignmentPolicy/ResultPolicy this
 * session already relied on. Any existing role can be granted these
 * permissions via the standard role/permission admin screens; admin
 * itself is granted all five by DatesheetPermissionsSeeder as a sensible
 * default, not because the role is hardcoded here.
 */
class DatesheetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('view-datesheet');
    }

    public function view(User $user, Datesheet $datesheet): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('create-datesheet');
    }

    /**
     * Adding/removing entries and submitting for review are all the same
     * "drafting" activity as creating the datesheet header -- gated by
     * EITHER create-datesheet or edit-datesheet, not edit-datesheet alone.
     * create-datesheet is "I can build a draft end to end, including
     * submitting it"; edit-datesheet is separately grantable for someone
     * who should be able to work on drafts they didn't personally create
     * (e.g. a second staff member covering for the creator) without also
     * being able to originate brand-new ones. Editing entries is only ever
     * meaningful while the datesheet is still a draft --
     * Datesheet::isEditable() enforces that separately.
     */
    public function update(User $user, Datesheet $datesheet): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('create-datesheet') || $user->hasPermission('edit-datesheet');
    }

    public function submit(User $user, Datesheet $datesheet): bool
    {
        return $this->update($user, $datesheet);
    }

    public function approve(User $user, Datesheet $datesheet): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('approve-datesheet');
    }

    public function reject(User $user, Datesheet $datesheet): bool
    {
        return $this->approve($user, $datesheet);
    }

    public function publish(User $user, Datesheet $datesheet): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('publish-datesheet');
    }

    public function revise(User $user, Datesheet $datesheet): bool
    {
        return $this->create($user);
    }
}
