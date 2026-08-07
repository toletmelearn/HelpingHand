<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;

class TeacherAvailabilityPolicy
{
    /**
     * Determine whether the user can view the teacher-availability screens
     * at all (the index/list of teachers, or their own grid).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    /**
     * Determine whether the user can view/edit a specific teacher's
     * availability grid. No model instance exists for this ability (the
     * "resource" is a teacher's set of TeacherAvailability rows, not one
     * row), so the target teacher id is passed as an extra authorize()
     * arg -- same pattern as TimetableSlotPolicy::create(). Admin may
     * manage any teacher; a teacher may only manage their own.
     */
    public function manage(User $user, ?int $teacherId = null): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (!$user->hasRole('teacher') || !$teacherId) {
            return false;
        }

        $teacher = Teacher::where('user_id', $user->id)->first();

        return $teacher && $teacher->id === $teacherId;
    }
}
