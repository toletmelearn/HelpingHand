<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StudentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admins, teachers, accountants, clerks, and users with view-students permission can view all students
        return $user->hasRole('admin') || $user->hasRole('teacher') || $user->hasAnyRole(['clerk', 'accountant']) || $user->hasPermission('view-students');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Student $student): bool
    {
        // Admins, teachers, accountants, clerks, and authorized users can view any student
        if ($user->hasRole('admin') || $user->hasRole('teacher') || $user->hasAnyRole(['clerk', 'accountant']) || $user->hasPermission('view-students')) {
            return true;
        }
        
        // Students can view their own record
        if ($user->hasRole('student') && $user->student && $user->student->id === $student->id) {
            return true;
        }
        
        // Parents can view their children's records
        if ($user->hasRole('parent')) {
            return $user->guardians->contains(function ($guardian) use ($student) {
                return $guardian->students->contains($student);
            });
        }
        
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Admins, teachers, and authorized users can create students
        return $user->hasRole('admin') || $user->hasRole('teacher') || $user->hasPermission('create-students');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Student $student): bool
    {
        // Admins, accountants, and authorized users can update any student
        if ($user->hasRole('admin') || $user->hasRole('accountant') || $user->hasPermission('edit-students')) {
            return true;
        }
        
        // Teachers can update students in their class
        if ($user->hasRole('teacher')) {
            return $user->teacher && $user->teacher->assignedClasses->contains(function ($class) use ($student) {
                return $class->students->contains($student);
            });
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Student $student): bool
    {
        // Admins and authorized users can delete students
        return $user->hasRole('admin') || $user->hasPermission('delete-students');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Student $student): bool
    {
        // Only admins can restore students
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Student $student): bool
    {
        // Only admins can permanently delete students
        return $user->hasRole('admin');
    }
}
