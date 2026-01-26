<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define permissions
        $permissions = [
            // Teacher permissions
            'can-be-class-teacher',
            'can-mark-attendance',
            'can-edit-attendance',
            'can-upload-marks',
            'can-edit-marks',
            'can-upload-syllabus',
            'can-approve-substitutions',
            'can-access-student-data',
            
            // Class teacher permissions
            'can-edit-class-student-data',
            'can-request-correction',
            'can-view-full-attendance-history',
            'can-view-fee-status',
            
            // Accountant permissions
            'can-manage-fees',
            'can-generate-fee-reports',
            'can-view-salary-data',
            'no-academic-access',
            
            // Admin permissions
            'full-access',
            'assign-permissions',
            'suspend-users',
            'access-audit-logs',
            
            // General permissions
            'view-fees',
            'create-fees',
            'edit-fees',
            'delete-fees',
            'view-fee-structures',
            'create-fee-structures',
            'edit-fee-structures',
            'delete-fee-structures',
            'view-students',
            'create-students',
            'edit-students',
            'delete-students',
            'view-teachers',
            'create-teachers',
            'edit-teachers',
            'delete-teachers',
            'view-exams',
            'create-exams',
            'edit-exams',
            'delete-exams',
            'view-results',
            'create-results',
            'edit-results',
            'delete-results',
            'view-attendance',
            'create-attendance',
            'edit-attendance',
            'delete-attendance',
            
            // Admit card permissions
            'view-admit-cards',
            'create-admit-cards',
            'edit-admit-cards',
            'delete-admit-cards',
            'view-admit-card-formats',
            'create-admit-card-formats',
            'edit-admit-card-formats',
            'delete-admit-card-formats',
            
            // Exam paper permissions
            'view-exam-papers',
            'create-exam-papers',
            'edit-exam-papers',
            'delete-exam-papers',
            'submit-exam-papers',
            'approve-exam-papers',
            'lock-exam-papers',
            'clone-exam-papers',
            'view-exam-paper-templates',
            'create-exam-paper-templates',
            'edit-exam-paper-templates',
            'delete-exam-paper-templates',
            'toggle-template-status',
            'preview-exam-paper-templates',
            
            // Daily teaching work permissions
            'view-daily-teaching-work',
            'create-daily-teaching-work',
            'edit-daily-teaching-work',
            'delete-daily-teaching-work',
            'download-daily-teaching-work-attachments',
            
            // Syllabus permissions
            'view-syllabi',
            'create-syllabi',
            'edit-syllabi',
            'delete-syllabi',
            'view-syllabus-progress-report',
        ];
        
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
        
        // Assign permissions to roles
        $adminRole = Role::where('name', 'admin')->first();
        $teacherRole = Role::where('name', 'teacher')->first();
        $classTeacherRole = Role::where('name', 'class-teacher')->first();
        $accountantRole = Role::where('name', 'accountant')->first();
        
        if ($adminRole) {
            foreach (Permission::all() as $permission) {
                $adminRole->grantPermission($permission->name);
            }
        }
        
        if ($teacherRole) {
            $teacherRole->grantPermission('can-upload-marks');
            $teacherRole->grantPermission('can-upload-syllabus');
            $teacherRole->grantPermission('can-mark-attendance');
            $teacherRole->grantPermission('view-students');
            $teacherRole->grantPermission('view-attendance');
            $teacherRole->grantPermission('view-daily-teaching-work');
            $teacherRole->grantPermission('create-daily-teaching-work');
            $teacherRole->grantPermission('download-daily-teaching-work-attachments');
        }
        
        if ($classTeacherRole) {
            $classTeacherRole->grantPermission('can-edit-class-student-data');
            $classTeacherRole->grantPermission('can-view-full-attendance-history');
            $classTeacherRole->grantPermission('can-view-fee-status');
            $classTeacherRole->grantPermission('view-students');
            $classTeacherRole->grantPermission('edit-students');
            $classTeacherRole->grantPermission('view-daily-teaching-work');
            $classTeacherRole->grantPermission('view-syllabi');
            $classTeacherRole->grantPermission('view-syllabus-progress-report');
        }
        
        if ($accountantRole) {
            $accountantRole->grantPermission('can-manage-fees');
            $accountantRole->grantPermission('can-generate-fee-reports');
            $accountantRole->grantPermission('view-fees');
            $accountantRole->grantPermission('create-fees');
            $accountantRole->grantPermission('edit-fees');
            $accountantRole->grantPermission('delete-fees');
            $accountantRole->grantPermission('view-fee-structures');
            $accountantRole->grantPermission('create-fee-structures');
            $accountantRole->grantPermission('edit-fee-structures');
            $accountantRole->grantPermission('delete-fee-structures');
            $accountantRole->grantPermission('view-admit-cards');
            $accountantRole->grantPermission('view-admit-card-formats');
            $accountantRole->grantPermission('view-daily-teaching-work');
        }
    }
}
