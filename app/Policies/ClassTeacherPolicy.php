<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Teacher;
use App\Models\Student;
use Illuminate\Auth\Access\Response;

class ClassTeacherPolicy
{
    /**
     * Determine whether the user can view any students in their assigned class.
     */
    public function viewAnyClassStudents(User $user): bool
    {
        return $user->hasRole(['admin', 'class-teacher']);
    }

    /**
     * Determine whether the user can view a specific student in their assigned class.
     */
    public function viewClassStudent(User $user, Student $student): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('class-teacher')) {
            // Check if the student belongs to a class assigned to this teacher
            $classTeacher = Teacher::where('user_id', $user->id)->first();
            if (!$classTeacher) {
                return false;
            }
            
            // Check if student's class is assigned to this teacher as class teacher
            $classIds = $classTeacher->classes()->pluck('class_management.id')->toArray();
            return in_array($student->class_id, $classIds);
        }
        
        return false;
    }

    /**
     * Determine whether the user can update a specific student in their assigned class.
     */
    public function updateClassStudent(User $user, Student $student): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('class-teacher')) {
            // Check if the student belongs to a class assigned to this teacher
            $classTeacher = Teacher::where('user_id', $user->id)->first();
            if (!$classTeacher) {
                return false;
            }
            
            // Check if student's class is assigned to this teacher as class teacher
            $classIds = $classTeacher->classes()->pluck('class_management.id')->toArray();
            return in_array($student->class_id, $classIds);
        }
        
        return false;
    }

    /**
     * Determine whether the user can view audit logs.
     */
    public function viewAuditLogs(User $user): bool
    {
        return $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can manage field permissions.
     */
    public function manageFieldPermissions(User $user): bool
    {
        return $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can view assigned classes.
     */
    public function viewAssignedClasses(User $user): bool
    {
        return $user->hasRole(['admin', 'class-teacher']);
    }

    /**
     * Determine whether the user can view student records.
     */
    public function viewStudentRecords(User $user): bool
    {
        return $user->hasRole(['admin', 'class-teacher']);
    }
}