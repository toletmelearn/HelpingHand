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
        ];
        
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }
    }
}
