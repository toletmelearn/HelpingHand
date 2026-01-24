<?php

namespace App\Policies;

use App\Models\ExamPaper;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ExamPaperPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher') || $user->hasRole('student');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ExamPaper $examPaper): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('teacher')) {
            return $user->teacher && (
                $examPaper->uploaded_by === $user->id ||
                $examPaper->class_section === $user->teacher->assigned_class
            );
        }
        
        if ($user->hasRole('student')) {
            return $user->student && $user->student->class === $examPaper->class_section;
        }
        
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ExamPaper $examPaper): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('teacher')) {
            return $examPaper->uploaded_by === $user->id;
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ExamPaper $examPaper): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('teacher')) {
            return $examPaper->uploaded_by === $user->id;
        }
        
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ExamPaper $examPaper): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ExamPaper $examPaper): bool
    {
        return $user->hasRole('admin');
    }
    
    public function submit(User $user, ExamPaper $examPaper): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('teacher')) {
            return $examPaper->uploaded_by === $user->id && $examPaper->status === 'draft';
        }
        
        return false;
    }
    
    public function approve(User $user, ExamPaper $examPaper): bool
    {
        return $user->hasRole('admin');
    }
    
    public function lock(User $user, ExamPaper $examPaper): bool
    {
        return $user->hasRole('admin');
    }
    
    public function clone(User $user, ExamPaper $examPaper): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('teacher')) {
            return $examPaper->uploaded_by === $user->id;
        }
        
        return false;
    }
}