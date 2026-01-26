<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        'App\Models\TeacherSubstitution' => 'App\Policies\TeacherSubstitutionPolicy',
        'App\Models\BellSchedule' => 'App\Policies\BellSchedulePolicy',
        'App\Models\SpecialDayOverride' => 'App\Policies\SpecialDayOverridePolicy',
        'App\Models\ClassTeacherAssignment' => 'App\Policies\ClassTeacherAssignmentPolicy',
        'App\Models\FieldPermission' => 'App\Policies\FieldPermissionPolicy',
        'App\Models\AuditLog' => 'App\Policies\AuditLogPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        //
    }
}