<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Guardian;
use App\Models\Role;

class User extends Authenticatable
{
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'role',
        'status',
        'last_login_at',
        'last_login_ip',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_login_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime'
    ];

    // Relationships
    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function teacher()
    {
        return $this->hasOne(Teacher::class);
    }
    
    public function lessonPlans()
    {
        return $this->hasMany(LessonPlan::class, 'teacher_id');
    }
    
    public function createdLessonPlans()
    {
        return $this->hasMany(LessonPlan::class, 'created_by');
    }
    
    public function modifiedLessonPlans()
    {
        return $this->hasMany(LessonPlan::class, 'modified_by');
    }

    public function guardians()
    {
        return $this->hasMany(Guardian::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }

    public function counselledEnquiries()
    {
        return $this->hasMany(AdmissionEnquiry::class, 'counsellor_id');
    }

    public function appointmentsToMeet()
    {
        return $this->hasMany(Appointment::class, 'teacher_id');
    }

    public function appointmentsCreated()
    {
        return $this->hasMany(Appointment::class, 'receptionist_id');
    }

    public function assignedCallLogs()
    {
        return $this->hasMany(CallLog::class, 'assigned_user_id');
    }

    public function couriersReceived()
    {
        return $this->hasMany(Courier::class, 'recipient_user_id');
    }

    public function gatePasses()
    {
        return $this->hasMany(GatePass::class, 'user_id');
    }

    public function gatePassesApproved()
    {
        return $this->hasMany(GatePass::class, 'approved_by');
    }

    // Methods for role management
    public function assignRole($roleName)
    {
        $role = Role::where('name', $roleName)->first();
        if ($role) {
            $this->roles()->attach($role->id);
        }
    }

    public function removeRole($roleName)
    {
        $role = Role::where('name', $roleName)->first();
        if ($role) {
            $this->roles()->detach($role->id);
        }
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles()->whereIn('name', ['super-admin', 'super_admin'])->exists();
    }

    public function hasRole($roleName)
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('roles')) {
            return false;
        }
        if (is_array($roleName)) {
            return $this->hasAnyRole($roleName);
        }
        
        $rolesToCheck = [$roleName];
        if ($roleName === 'staff') {
            $rolesToCheck = ['staff', 'clerk', 'accountant'];
        }
        
        return $this->roles()->whereIn('name', $rolesToCheck)->exists();
    }

    public function hasAnyRole($roles)
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('roles')) {
            return false;
        }
        $roles = is_array($roles) ? $roles : [$roles];
        
        // Expand 'staff' to include 'clerk' and 'accountant'
        $expandedRoles = [];
        foreach ($roles as $role) {
            if ($role === 'staff') {
                $expandedRoles[] = 'staff';
                $expandedRoles[] = 'clerk';
                $expandedRoles[] = 'accountant';
            } else {
                $expandedRoles[] = $role;
            }
        }
        
        return $this->roles()->whereIn('name', array_unique($expandedRoles))->exists();
    }

    public function hasAllRoles($roles)
    {
        $roles = is_array($roles) ? $roles : [$roles];
        foreach ($roles as $role) {
            if (!$this->hasRole($role)) {
                return false;
            }
        }
        return true;
    }
    
    public function notifications()
    {
        return $this->morphMany(\Illuminate\Notifications\DatabaseNotification::class, 'notifiable')
                    ->orderBy('created_at', 'desc');
    }
    
    public function unreadNotifications()
    {
        return $this->morphMany(\Illuminate\Notifications\DatabaseNotification::class, 'notifiable')
                    ->whereNull('read_at')
                    ->orderBy('created_at', 'desc');
    }
    
    // Get all permissions for the user
    public function getAllPermissions()
    {
        $permissions = collect();
        
        foreach ($this->roles as $role) {
            $permissions = $permissions->merge($role->permissions);
        }
        
        return $permissions->unique('id');
    }
    
    // Check if user is class teacher for a specific class
    public function isClassTeacherForClass($className)
    {
        return $this->classTeacherAssignments()
                    ->where('assigned_class', $className)
                    ->where('is_active', true)
                    ->exists();
    }
    
    // Get the active class teacher assignments for this user
    public function classTeacherAssignments()
    {
        return $this->hasMany(\App\Models\ClassTeacherAssignment::class, 'teacher_id');
    }
    
    // Get the class teacher assignment for a specific class
    public function getClassTeacherAssignmentForClass($className)
    {
        return $this->classTeacherAssignments()
                    ->where('assigned_class', $className)
                    ->where('is_active', true)
                    ->first();
    }
    
    public function scopeRole($query, $roleName)
    {
        return $query->whereHas('roles', function($q) use ($roleName) {
            $q->where('name', $roleName);
        });
    }
    
    public function hasPermission($permissionName)
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('roles') || !\Illuminate\Support\Facades\Schema::hasTable('permissions')) {
            return false;
        }
        foreach ($this->roles as $role) {
            if ($role->permissions()->where('name', $permissionName)->exists()) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Alias for hasPermission to support Spatie Laravel Permission naming conventions.
     */
    public function hasPermissionTo($permissionName)
    {
        return $this->hasPermission($permissionName);
    }
}