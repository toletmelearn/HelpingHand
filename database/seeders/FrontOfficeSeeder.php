<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class FrontOfficeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create or retrieve Front Office permissions
        $permissions = [
            'view-front-office-dashboard' => 'Access to Front Office dashboard analytics and widgets',
            'manage-admission-enquiries' => 'Create, edit and follow up on candidate enquiries',
            'manage-visitors' => 'Check-in and check-out campus visitors and print passes',
            'manage-calls' => 'Log incoming and outgoing telephone calls and outcomes',
            'manage-appointments' => 'Schedule parent-teacher meetings and check conflicts',
            'manage-gate-passes' => 'Verify and issue gate passes for students, staff and vehicles',
            'manage-courier' => 'Log courier and post records',
            'manage-lost-found' => 'Record lost and found items and claims details',
            'verify-gate-passes' => 'Verify exits at security gates and manage gate terminal',
            'manage-guard-duties' => 'Assign daily security guard shift/gate duties',
        ];

        foreach ($permissions as $name => $desc) {
            Permission::firstOrCreate(['name' => $name]);
        }

        // 2. Create Receptionist role
        $receptionistRole = Role::firstOrCreate([
            'name' => 'receptionist',
        ], [
            'display_name' => 'Receptionist',
            'description' => 'Front office coordinator and operations controller',
        ]);

        // 3. Assign permissions to Receptionist role
        foreach (array_keys($permissions) as $permissionName) {
            $receptionistRole->grantPermission($permissionName);
        }

        // 3.5. Create Guard role and assign verify-gate-passes permission
        $guardRole = Role::firstOrCreate([
            'name' => 'guard',
        ], [
            'display_name' => 'Security Guard',
            'description' => 'Campus security guard on gate duty',
        ]);
        $guardRole->grantPermission('verify-gate-passes');

        // 4. Grant all Front Office permissions to Admin role as well
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            foreach (array_keys($permissions) as $permissionName) {
                $adminRole->grantPermission($permissionName);
            }
        }
    }
}
