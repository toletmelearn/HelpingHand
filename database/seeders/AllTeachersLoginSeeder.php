<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Teacher;
use App\Models\TeacherLogin;

class AllTeachersLoginSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * This seeder creates teacher_login entries for ALL teachers who don't have one.
     * Username: teacher's phone number
     * Default password: 123456 (will force password change on first login)
     */
    public function run(): void
    {
        $this->command->info('🔄 Creating teacher logins for all teachers...');

        // Get all teachers who don't have a login yet
        $teachersWithoutLogin = Teacher::whereNotIn('id', function($query) {
            $query->select('teacher_id')->from('teacher_logins');
        })->get();

        if ($teachersWithoutLogin->isEmpty()) {
            $this->command->warn('⚠️  All teachers already have login accounts!');
            return;
        }

        $created = 0;
        $skipped = 0;

        foreach ($teachersWithoutLogin as $teacher) {
            // Skip if teacher doesn't have phone number
            if (empty($teacher->phone)) {
                $this->command->warn("⚠️  Skipped Teacher #{$teacher->id} ({$teacher->name}) - No phone number");
                $skipped++;
                continue;
            }

            // Check if username already exists (for safety)
            $existingLogin = TeacherLogin::where('username', $teacher->phone)->first();
            if ($existingLogin) {
                $this->command->warn("⚠️  Skipped Teacher #{$teacher->id} - Username {$teacher->phone} already exists");
                $skipped++;
                continue;
            }

            // Create teacher login
            TeacherLogin::create([
                'teacher_id' => $teacher->id,
                'school_id' => $teacher->school_id ?? null,
                'username' => $teacher->phone,
                'password' => Hash::make('123456'), // Default password
                'status' => 'active',
                'force_password_change' => true, // Force password change on first login
            ]);

            $created++;
            $this->command->info("✅ Created login for: {$teacher->name} (Username: {$teacher->phone})");
        }

        $this->command->info("\n📊 Summary:");
        $this->command->info("   ✅ Created: {$created} logins");
        if ($skipped > 0) {
            $this->command->info("   ⚠️  Skipped: {$skipped} teachers");
        }
        $this->command->info("\n🔑 Default credentials:");
        $this->command->info("   Username: Teacher's phone number");
        $this->command->info("   Password: 123456");
        $this->command->info("   (Password change will be forced on first login)");
    }
}
