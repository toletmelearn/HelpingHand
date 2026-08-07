<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TimetableSlot;
use App\Models\User;

class TimetableSlotPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TimetableSlot $timetableSlot): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    /**
     * Determine whether the user can create models. TimetableController::
     * store() doesn't have a model instance yet (it's an updateOrCreate
     * keyed on school_class_id/section_id/bell_timing_id), so the
     * class/section being written is passed as extra authorize() args --
     * previously any teacher could write to any class's timetable;
     * narrowed to only class-sections the teacher is actually assigned to
     * per teacher_class_subject_assignments (remediation deferred
     * register item).
     */
    public function create(User $user, ?int $schoolClassId = null, ?int $sectionId = null): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$user->hasRole('teacher') || !$schoolClassId) {
            return false;
        }

        return $this->teacherAssignedToClassSection($user, $schoolClassId, $sectionId);
    }

    /**
     * Determine whether the user can update the model. Not currently
     * invoked by TimetableController (store() only authorizes 'create'
     * for its updateOrCreate), but narrowed for consistency and for
     * future phases (T2+) that may add a dedicated update action.
     */
    public function update(User $user, TimetableSlot $timetableSlot): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$user->hasRole('teacher')) {
            return false;
        }

        return $this->teacherAssignedToClassSection($user, $timetableSlot->school_class_id, $timetableSlot->section_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TimetableSlot $timetableSlot): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * T4b: triggering GenerateTimetableJob for a whole class is a bigger
     * action than placing one slot (it replaces that class's entire draft
     * proposal), and publishing/discarding one is exactly the action the
     * plan calls "admin-only" for PUBLISH -- both gated the same way here
     * for consistency, rather than letting a teacher force-regenerate or
     * discard a class's draft.
     */
    public function generate(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function publish(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * A teacher may write a slot for a class-section they hold any
     * assignment for. Assignments with a null section_id are treated as
     * covering the whole class (matches any requested section, including
     * none); a null requested section_id (writing at the class level,
     * unscoped) is satisfied by any assignment to that class.
     */
    private function teacherAssignedToClassSection(User $user, int $schoolClassId, ?int $sectionId): bool
    {
        $teacher = Teacher::where('user_id', $user->id)->first();

        if (!$teacher) {
            return false;
        }

        return TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
            ->where('class_id', $schoolClassId)
            ->where(function ($query) use ($sectionId) {
                $query->whereNull('section_id');
                if ($sectionId) {
                    $query->orWhere('section_id', $sectionId);
                }
            })
            ->exists();
    }
}
