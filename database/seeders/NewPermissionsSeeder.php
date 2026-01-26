<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NewPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define new permissions
        $permissions = [
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
        
        foreach ($permissions as $permissionName) {
            \App\Models\Permission::firstOrCreate(['name' => $permissionName]);
        }
        
        // Assign permissions to roles
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $teacherRole = \App\Models\Role::where('name', 'teacher')->first();
        $classTeacherRole = \App\Models\Role::where('name', 'class-teacher')->first();
        $accountantRole = \App\Models\Role::where('name', 'accountant')->first();
        
        if ($adminRole) {
            foreach ($permissions as $permission) {
                $adminRole->grantPermission($permission);
            }
        }
        
        if ($teacherRole) {
            $teacherRole->grantPermission('view-daily-teaching-work');
            $teacherRole->grantPermission('create-daily-teaching-work');
            $teacherRole->grantPermission('download-daily-teaching-work-attachments');
        }
        
        if ($classTeacherRole) {
            $classTeacherRole->grantPermission('view-daily-teaching-work');
            $classTeacherRole->grantPermission('view-syllabi');
            $classTeacherRole->grantPermission('view-syllabus-progress-report');
        }
        
        if ($accountantRole) {
            $accountantRole->grantPermission('view-daily-teaching-work');
        }
    }
}
