<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;

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
            if (! $classTeacher) {
                return false;
            }

            // Academic setup completion: Teacher::classes() is backed by the
            // class_teacher pivot table, which has zero rows in real use --
            // this check was permanently false for every teacher. The real
            // signal is teacher_class_subject_assignments.is_class_teacher
            // (see Teacher::isClassTeacherOfSchoolClass()), which is keyed
            // directly to school_classes.id -- no legacy_class_map
            // translation needed since that table was never keyed to
            // class_management in the first place.
            return $classTeacher->isClassTeacherOfSchoolClass((int) $student->school_class_id);
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
            if (! $classTeacher) {
                return false;
            }

            // Academic setup completion: Teacher::classes() is backed by the
            // class_teacher pivot table, which has zero rows in real use --
            // this check was permanently false for every teacher. The real
            // signal is teacher_class_subject_assignments.is_class_teacher
            // (see Teacher::isClassTeacherOfSchoolClass()), which is keyed
            // directly to school_classes.id -- no legacy_class_map
            // translation needed since that table was never keyed to
            // class_management in the first place.
            return $classTeacher->isClassTeacherOfSchoolClass((int) $student->school_class_id);
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
