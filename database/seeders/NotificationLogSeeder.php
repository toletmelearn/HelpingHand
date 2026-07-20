<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if the table exists
        if (!Schema::hasTable('notification_logs')) {
            $this->command->info('Notification logs table does not exist. Please run migrations first.');
            return;
        }
        
        // Check if there are any notification settings
        $notificationSetting = DB::table('notification_settings')->first();
        
        if (!$notificationSetting) {
            $this->command->info('No notification settings found. Please seed notification settings first.');
            return;
        }
        
        // Get a student ID for recipient_id
        $student = DB::table('students')->first();
        $studentId = $student ? $student->id : null;
        
        // Define default notification logs
        $logs = [
            [
                'notification_setting_id' => $notificationSetting->id,
                'recipient_type' => 'student',
                'recipient_id' => $studentId,
                'notification_type' => 'email',
                'subject' => 'Welcome to Our School',
                'message' => 'Welcome to our school! We are excited to have you as part of our community.',
                'status' => 'pending',
                'sent_at' => null,
                'failed_reason' => null,
                'retry_count' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'notification_setting_id' => $notificationSetting->id,
                'recipient_type' => 'parent',
                'recipient_id' => $studentId,
                'notification_type' => 'email',
                'subject' => 'Fee Due Reminder',
                'message' => 'This is a reminder that the monthly fee is due by the end of this week.',
                'status' => 'pending',
                'sent_at' => null,
                'failed_reason' => null,
                'retry_count' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'notification_setting_id' => $notificationSetting->id,
                'recipient_type' => 'teacher',
                'recipient_id' => null,
                'notification_type' => 'email',
                'subject' => 'Staff Meeting Reminder',
                'message' => 'Reminder: Staff meeting scheduled for tomorrow at 10:00 AM in the conference room.',
                'status' => 'pending',
                'sent_at' => null,
                'failed_reason' => null,
                'retry_count' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];
        
        // Insert logs if they don't exist
        foreach ($logs as $log) {
            $exists = DB::table('notification_logs')
                ->where('notification_setting_id', $log['notification_setting_id'])
                ->where('recipient_type', $log['recipient_type'])
                ->where('message', $log['message'])
                ->exists();
                
            if (!$exists) {
                DB::table('notification_logs')->insert($log);
            }
        }
        
        $this->command->info('Notification logs seeded successfully.');
    }
}
