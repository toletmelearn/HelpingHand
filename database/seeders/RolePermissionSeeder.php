<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Get roles
        $adminRole = Role::where('name', 'admin')->first();
        $teacherRole = Role::where('name', 'teacher')->first();
        
        if ($adminRole) {
            // Assign all permissions to admin
            $permissions = Permission::all();
            foreach ($permissions as $permission) {
                if (!$adminRole->hasPermission($permission->name)) {
                    $adminRole->grantPermission($permission);
                }
            }
            echo "Admin role granted all permissions\n";
        }
        
        if ($teacherRole) {
            // Assign teacher-specific permissions
            $teacherPermissions = [
                'view-students',
                'view-attendance',
                'view-exams',
                'view-results',
                'view-timetable',
                'view-syllabus',
                'view-lesson-plans',
                'view-notifications'
            ];
            
            foreach ($teacherPermissions as $permName) {
                $permission = Permission::where('name', $permName)->first();
                if ($permission && !$teacherRole->hasPermission($permission)) {
                    $teacherRole->grantPermission($permission);
                }
            }
            echo "Teacher role granted specific permissions\n";
        }
    }
}
