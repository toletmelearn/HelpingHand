<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = env('INITIAL_ADMIN_EMAIL', 'admin@school.com');
        $adminPassword = env('INITIAL_ADMIN_PASSWORD', 'password');

        $admin = User::where('email', $adminEmail)->first();
        
        if (!$admin) {
            $admin = User::create([
                'name' => 'Admin',
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
            ]);
                        
            echo "Admin user created successfully!\n";
            echo "Email: {$adminEmail}\n";
            echo "Password: (Initial password config)\n";
        } else {
            echo "Admin user already exists.\n";
            echo "Email: {$adminEmail}\n";
        }
    }
}