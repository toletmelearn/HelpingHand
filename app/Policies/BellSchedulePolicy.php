<?php

namespace App\Policies;

use App\Models\BellSchedule;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BellSchedulePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('teacher') || $user->hasRole('staff');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BellSchedule $bellSchedule): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('teacher')) {
            return true; // Teachers can view bell schedules
        }
        
        if ($user->hasRole('student')) {
            return true; // Students can view bell schedules
        }
        
        if ($user->hasRole('staff')) {
            return true; // Staff can view bell schedules
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
    public function update(User $user, BellSchedule $bellSchedule): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BellSchedule $bellSchedule): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, BellSchedule $bellSchedule): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, BellSchedule $bellSchedule): bool
    {
        return $user->hasRole('admin');
    }
    
    public function manageSchedules(User $user): bool
    {
        return $user->hasRole('admin');
    }
    
    public function viewLiveMonitor(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
