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
        // Existing policies
        'App\Models\TeacherSubstitution' => 'App\Policies\TeacherSubstitutionPolicy',
        'App\Models\BellSchedule' => 'App\Policies\BellSchedulePolicy',
        'App\Models\SpecialDayOverride' => 'App\Policies\SpecialDayOverridePolicy',
        'App\Models\ClassTeacherAssignment' => 'App\Policies\ClassTeacherAssignmentPolicy',
        'App\Models\ClassManagement' => 'App\Policies\ClassManagementPolicy',
        'App\Models\Book' => 'App\Policies\BookPolicy',
        'App\Models\BookIssue' => 'App\Policies\BookIssuePolicy',
        'App\Models\LessonPlan' => 'App\Policies\LessonPlanPolicy',
        'App\Models\FieldPermission' => 'App\Policies\FieldPermissionPolicy',
        'App\Models\AuditLog' => 'App\Policies\AuditLogPolicy',
        
        // Newly added policies
        'App\Models\Budget' => 'App\Policies\BudgetPolicy',
        'App\Models\Expense' => 'App\Policies\ExpensePolicy',
        'App\Models\StudentStatus' => 'App\Policies\StudentStatusPolicy',
        'App\Models\AdminConfiguration' => 'App\Policies\AdminConfigurationPolicy',
        
        // Additional policies that should be registered
        'App\Models\Student' => 'App\Policies\StudentPolicy',
        'App\Models\Teacher' => 'App\Policies\TeacherPolicy',
        'App\Models\Attendance' => 'App\Policies\AttendancePolicy',
        'App\Models\Fee' => 'App\Policies\FeePolicy',
        'App\Models\FeeStructure' => 'App\Policies\FeeStructurePolicy',
        'App\Models\ExamPaper' => 'App\Policies\ExamPaperPolicy',
        'App\Models\ExamPaperTemplate' => 'App\Policies\ExamPaperTemplatePolicy',
        'App\Models\AdmitCard' => 'App\Policies\AdmitCardPolicy',
        'App\Models\AdmitCardFormat' => 'App\Policies\AdmitCardFormatPolicy',
        'App\Models\Syllabus' => 'App\Policies\SyllabusPolicy',
        'App\Models\DailyTeachingWork' => 'App\Policies\DailyTeachingWorkPolicy',
        'App\Models\BellTiming' => 'App\Policies\BellTimingPolicy',
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