<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Attendance;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Auth;

class AttendancePolicy
{
    use HandlesAuthorization;

    /**
     * The acting teacher under the 'teacher' guard, if any. The
     * teacher-portal identity is authenticated on a guard separate from
     * the default 'web' guard $user parameter Laravel's Gate injects
     * into every policy method -- teacher-specific branches below must
     * resolve it explicitly rather than assuming $user is ever populated
     * for a request that only holds a teacher-guard session (it never
     * is). Mirrors the exact identity resolution TeacherAttendanceController
     * already uses elsewhere (Auth::guard('teacher')->user()->teacher).
     */
    private function resolveTeacher(): ?Teacher
    {
        $teacherLogin = Auth::guard('teacher')->user();

        return $teacherLogin ? $teacherLogin->teacher : null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view-attendance');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Attendance $attendance): bool
    {
        // Admins and users with view-attendance permission can view all attendance
        if ($user && ($user->hasRole('admin') || $user->hasPermission('view-attendance'))) {
            return true;
        }

        // Parents can view their children's attendance
        if ($user && $user->hasRole('parent')) {
            return $attendance->student->parent_id == $user->id;
        }

        // Teachers can view attendance they marked or for their classes.
        // Resolved via the teacher guard (see resolveTeacher()), and
        // class ownership via TeacherClassSubjectAssignment -- the
        // `teachers` table has no `class_id` column, so the previous
        // $teacher->class_id comparison could never match real data.
        $teacher = $this->resolveTeacher();
        if ($teacher) {
            if ($attendance->marked_by == $teacher->id) {
                return true;
            }

            $classId = $attendance->student->school_class_id ?? null;

            return $classId && TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
                ->where('class_id', $classId)
                ->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create-attendance') || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(?User $user, Attendance $attendance): bool
    {
        // Admins and users with edit-attendance permission can update all attendance
        if ($user && ($user->hasRole('admin') || $user->hasPermission('edit-attendance'))) {
            return true;
        }

        // Teachers can update attendance they marked (within 24 hours).
        // Resolved via the teacher guard (see resolveTeacher()) instead
        // of Teacher::where('user_id', $user->id) -- editAttendance()/
        // updateAttendance() are reached exclusively through the
        // 'teacher' guard, so $user (the default 'web' guard) is always
        // null there and this branch could never previously be reached.
        $teacher = $this->resolveTeacher();
        if ($teacher && $attendance->marked_by == $teacher->id) {
            return $attendance->created_at->addDay()->isFuture();
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Attendance $attendance): bool
    {
        // Admins and users with delete-attendance permission can delete attendance
        return $user->hasRole('admin') || $user->hasPermission('delete-attendance');
    }

    /**
     * Determine whether the user can mark attendance for a class.
     */
    public function markAttendance(User $user, $classId): bool
    {
        if ($user->hasRole('admin') || $user->hasPermission('create-attendance')) {
            return true;
        }
        
        if ($user->hasRole('teacher')) {
            $teacher = Teacher::where('user_id', $user->id)->first();
            if ($teacher) {
                return $teacher->class_id == $classId;
            }
        }
        
        return false;
    }

    /**
     * Determine whether the user can view attendance reports.
     */
    public function viewReports(User $user): bool
    {
        return $user->hasPermission('view-attendance-reports');
    }

    /**
     * Determine whether the user can export attendance data.
     */
    public function export(?User $user): bool
    {
        if ($user && $user->hasPermission('export-attendance')) {
            return true;
        }

        // exportAttendance() is reached exclusively through the 'teacher'
        // guard, so $user is always null there. This ability has no
        // specific target instance to scope ownership against (the same
        // broad-capability shape as viewAny()/create() elsewhere in this
        // policy) -- any authenticated teacher may reach it.
        return $this->resolveTeacher() !== null;
    }
}