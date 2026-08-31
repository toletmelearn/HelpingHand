<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Confirmed decision: no new "Exam Cell" role -- these five permissions
 * are assignable to any existing role via the standard role/permission
 * admin screens. Defaults granted here are a sensible starting point, not
 * a hardcoded assumption that every admin-like role needs every ability
 * ("Do not assume every Admin role should have every permission"):
 * admin gets all five; principal (the natural real-world approver) gets
 * create/edit/approve/publish/view; teacher and class-teacher get
 * view-datesheet only (their portal is view-only regardless, per
 * DatesheetPolicy, but the permission is still what gates
 * viewAny()/view() for any staff-side screen).
 */
class DatesheetPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'create-datesheet',
            'edit-datesheet',
            'approve-datesheet',
            'publish-datesheet',
            'view-datesheet',
        ];

        foreach ($permissions as $permissionName) {
            \App\Models\Permission::firstOrCreate(['name' => $permissionName]);
        }

        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $principalRole = \App\Models\Role::where('name', 'principal')->first();
        $teacherRole = \App\Models\Role::where('name', 'teacher')->first();
        $classTeacherRole = \App\Models\Role::where('name', 'class-teacher')->first();

        if ($adminRole) {
            foreach ($permissions as $permission) {
                $adminRole->grantPermission($permission);
            }
        }

        if ($principalRole) {
            foreach ($permissions as $permission) {
                $principalRole->grantPermission($permission);
            }
        }

        if ($teacherRole) {
            $teacherRole->grantPermission('view-datesheet');
        }

        if ($classTeacherRole) {
            $classTeacherRole->grantPermission('view-datesheet');
        }
    }
}
