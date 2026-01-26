<?php

namespace App\Policies;

use App\Models\Syllabus;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SyllabusPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher') || $user->hasRole('class-teacher') || $user->hasRole('student');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Syllabus $syllabus): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('teacher')) {
            return $user->teacher && $syllabus->subject === $user->teacher->assigned_subject;
        }
        
        if ($user->hasRole('class-teacher')) {
            return $user->teacher && $syllabus->class_name === $user->teacher->assigned_class;
        }
        
        if ($user->hasRole('student')) {
            return $user->student && (
                $syllabus->class_name === $user->student->class &&
                $syllabus->section === $user->student->section
            );
        }
        
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Syllabus $syllabus): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Syllabus $syllabus): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Syllabus $syllabus): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Syllabus $syllabus): bool
    {
        return $user->hasRole('admin');
    }
    
    public function viewProgressReport(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('class-teacher');
    }
}
