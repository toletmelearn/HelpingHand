<?php

namespace App\Policies;

use App\Models\ExamPaperTemplate;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ExamPaperTemplatePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ExamPaperTemplate $examPaperTemplate): bool
    {
        return $user->hasRole('admin');
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
    public function update(User $user, ExamPaperTemplate $examPaperTemplate): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ExamPaperTemplate $examPaperTemplate): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ExamPaperTemplate $examPaperTemplate): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ExamPaperTemplate $examPaperTemplate): bool
    {
        return $user->hasRole('admin');
    }
    
    /**
     * Determine whether the user can toggle template status.
     */
    public function toggleStatus(User $user, ExamPaperTemplate $examPaperTemplate): bool
    {
        return $user->hasRole('admin');
    }
    
    /**
     * Determine whether the user can preview template.
     */
    public function preview(User $user, ExamPaperTemplate $examPaperTemplate): bool
    {
        return $user->hasRole('admin');
    }
}
