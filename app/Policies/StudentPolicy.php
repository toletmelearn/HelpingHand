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
        // Admins and teachers can view all students
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Student $student): bool
    {
        // Admins and teachers can view any student
        if ($user->hasRole('admin') || $user->hasRole('teacher')) {
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
        // Only admins and teachers can create students
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Student $student): bool
    {
        // Admins can update any student
        if ($user->hasRole('admin')) {
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
        // Only admins can delete students
        return $user->hasRole('admin');
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
