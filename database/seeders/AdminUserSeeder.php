<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Check if admin user already exists
        $admin = User::where('email', 'admin@school.com')->first();
        
        if (!$admin) {
            $admin = User::create([
                'name' => 'Admin',
                'email' => 'admin@school.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
                        
            echo "Admin user created successfully!\n";
            echo "Email: admin@school.com\n";
            echo "Password: password\n";
        } else {
            echo "Admin user already exists.\n";
            echo "Email: admin@school.com\n";
            echo "Password: password\n";
        }
    }
}