<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Extends the Defaulter Registry so Class Teacher and Receptionist can
     * see and act on it (scoped/limited), and Admin/Principal/Accountant
     * can grant the exam-restriction override.
     *
     * 'class-teacher' is referenced throughout PermissionSeeder.php
     * (ClassTeacherPolicy, grantPermission calls at lines 257-266) but was
     * never actually created by any seeder -- Role::where('name',
     * 'class-teacher')->first() has always silently returned null there.
     * This fills that pre-existing gap rather than inventing a new role,
     * since the permission-granting code already assumed it would exist.
     */
    public function up(): void
    {
        $principalRole = Role::firstOrCreate(
            ['name' => 'principal'],
            ['display_name' => 'Principal', 'description' => 'School principal -- academic and disciplinary authority']
        );

        $classTeacherRole = Role::firstOrCreate(
            ['name' => 'class-teacher'],
            ['display_name' => 'Class Teacher', 'description' => 'Teacher assigned as a class\'s form/class teacher']
        );

        $overridePermission = Permission::firstOrCreate(
            ['name' => 'override-exam-restriction'],
            ['module' => 'defaulters', 'label' => 'Grant Exam Restriction Override']
        );

        $communicatePermission = Permission::firstOrCreate(
            ['name' => 'communicate-defaulters'],
            ['module' => 'defaulters', 'label' => 'Send Defaulter Communications (no stage override)']
        );

        $principalRole->grantPermission('view-defaulters');
        $principalRole->grantPermission($overridePermission->name);

        // Admin does NOT get new permissions automatically -- PermissionSeeder
        // is a pre-existing seeder unaware of this migration's new
        // permission, and 'admin' is a distinct role from 'super-admin'
        // (the only one that bypasses permission checks entirely). Grant
        // explicitly so Admin/Principal/Accountant can all grant the exam
        // exception, matching the requirement this was built for.
        foreach (['admin', 'accountant'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->grantPermission($overridePermission->name);
            }
        }

        // Deliberately NOT granted 'view-defaulters' -- that's the unscoped
        // "see every student" permission (Admin/Principal/Accountant/Clerk).
        // Class Teacher/Receptionist get communicate-defaulters only, and
        // DefaulterController::scopedClassIds() narrows what they see to
        // their own class roster (Class Teacher) or leaves it unscoped at
        // the front-office level (Receptionist has no linked Teacher record).
        foreach ([$classTeacherRole, Role::where('name', 'teacher')->first(), Role::where('name', 'receptionist')->first()] as $role) {
            if (!$role) {
                continue;
            }
            $role->grantPermission($communicatePermission->name);
        }
    }

    public function down(): void
    {
        // Roles/permissions are shared reference data -- intentionally not
        // deleted on rollback, matching this codebase's existing convention
        // for permission-seeding migrations (e.g. 2026_06_25_100000-style).
    }
};
