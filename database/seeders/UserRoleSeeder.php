<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        // Assign admin role to the admin user (ID 1)
        $adminUser = User::find(1);
        if ($adminUser) {
            $adminRole = Role::where('name', 'admin')->first();
            if ($adminRole) {
                // Detach any existing roles and attach the admin role
                $adminUser->roles()->detach();
                $adminUser->roles()->attach($adminRole->id);
            }
        }
        
        // You can add more role assignments here as needed
    }
}
