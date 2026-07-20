<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Attendance;
use App\Models\Teacher;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendancePolicy
{
    use HandlesAuthorization;

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
    public function view(User $user, Attendance $attendance): bool
    {
        // Admins and users with view-attendance permission can view all attendance
        if ($user->hasRole('admin') || $user->hasPermission('view-attendance')) {
            return true;
        }
        
        // Teachers can view attendance they marked or for their classes
        if ($user->hasRole('teacher')) {
            $teacher = Teacher::where('user_id', $user->id)->first();
            if ($teacher) {
                return $attendance->marked_by == $teacher->id || 
                       $attendance->student->class_id == $teacher->class_id;
            }
        }
        
        // Parents can view their children's attendance
        if ($user->hasRole('parent')) {
            return $attendance->student->parent_id == $user->id;
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
    public function update(User $user, Attendance $attendance): bool
    {
        // Admins and users with edit-attendance permission can update all attendance
        if ($user->hasRole('admin') || $user->hasPermission('edit-attendance')) {
            return true;
        }
        
        // Teachers can update attendance they marked (within 24 hours)
        if ($user->hasRole('teacher')) {
            $teacher = Teacher::where('user_id', $user->id)->first();
            if ($teacher && $attendance->marked_by == $teacher->id) {
                return $attendance->created_at->addDay()->isFuture();
            }
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
    public function export(User $user): bool
    {
        return $user->hasPermission('export-attendance');
    }
}