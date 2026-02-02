<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AdminUserSeeder::class,
            SchoolClassSeeder::class,
            SampleDataSeeder::class,
            UserRoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            LibrarySettingSeeder::class,
            AdmitCardFormatSeeder::class,
            ExamSeeder::class,
            NewPermissionsSeeder::class,
            LessonPlanSeeder::class, // Added for Lesson Plan Management System
            BiometricSettingsSeeder::class, // Added for Biometric System
        ]);
    }
}
