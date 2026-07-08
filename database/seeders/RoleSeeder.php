<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'super-admin',
                'display_name' => 'Super Administrator',
                'description' => 'Unrestricted system owner with override access to every module',
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'System administrator with full access',
            ],
            [
                'name' => 'teacher',
                'display_name' => 'Teacher',
                'description' => 'Teacher with access to teaching features',
            ],
            [
                'name' => 'student',
                'display_name' => 'Student',
                'description' => 'Student with access to learning features',
            ],
            [
                'name' => 'parent',
                'display_name' => 'Parent',
                'description' => 'Parent with access to student information',
            ],
            [
                'name' => 'accountant',
                'display_name' => 'Accountant',
                'description' => 'Accountant with access to fee collection, bookkeeping, reconciliation and financial records',
            ],
            [
                'name' => 'clerk',
                'display_name' => 'Clerk',
                'description' => 'Clerk with access to student admissions, daily operations and administrative data entry',
            ],
        ];
        
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }
    }
}
