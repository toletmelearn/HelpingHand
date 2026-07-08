<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if the table exists
        if (!Schema::hasTable('notification_settings')) {
            $this->command->info('Notification settings table does not exist. Please run migrations first.');
            return;
        }
        
        // Get a user ID for created_by field
        $user = DB::table('users')->first();
        $userId = $user ? $user->id : null;
        
        if (!$userId) {
            $this->command->info('No users found. Please create a user first.');
            return;
        }
        
        // Define default notification settings
        $notifications = [
            [
                'event_type' => 'student_admission',
                'notification_type' => 'email',
                'is_enabled' => true,
                'template_subject' => 'New Student Admission',
                'template_body' => 'A new student {{student_name}} has been admitted to class {{class}}.',
                'recipients' => json_encode(['admins', 'teachers']),
                'conditions' => null,
                'schedule_type' => 'immediate',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'event_type' => 'fee_due',
                'notification_type' => 'both',
                'is_enabled' => true,
                'template_subject' => 'Fee Due Reminder',
                'template_body' => 'Fee payment of {{amount}} is due for {{student_name}} by {{due_date}}.',
                'recipients' => json_encode(['parents']),
                'conditions' => json_encode(['days_before_due' => 7]),
                'schedule_type' => 'daily',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'event_type' => 'exam_scheduled',
                'notification_type' => 'email',
                'is_enabled' => true,
                'template_subject' => 'Exam Schedule Notification',
                'template_body' => 'Exam for {{subject}} is scheduled on {{date}} at {{time}} in {{room}}.',
                'recipients' => json_encode(['students', 'parents']),
                'conditions' => null,
                'schedule_type' => 'immediate',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'event_type' => 'attendance_low',
                'notification_type' => 'email',
                'is_enabled' => true,
                'template_subject' => 'Low Attendance Alert',
                'template_body' => 'Student {{student_name}} has attendance below {{threshold}}% in {{subject}}.',
                'recipients' => json_encode(['parents', 'class_teacher']),
                'conditions' => json_encode(['attendance_threshold' => 75]),
                'schedule_type' => 'weekly',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'event_type' => 'birthday',
                'notification_type' => 'email',
                'is_enabled' => true,
                'template_subject' => 'Happy Birthday!',
                'template_body' => 'Happy Birthday {{student_name}}! Wishing you a wonderful day.',
                'recipients' => json_encode(['students', 'parents', 'class_teacher']),
                'conditions' => null,
                'schedule_type' => 'immediate',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'event_type' => 'admission_enquiry_received',
                'notification_type' => 'both',
                'is_enabled' => true,
                'template_subject' => 'We received your admission enquiry',
                'template_body' => 'Dear {{parent_name}}, thank you for your enquiry for {{candidate_name}}. Our admissions team will be in touch shortly.',
                'recipients' => json_encode(['parents']),
                'conditions' => null,
                'schedule_type' => 'immediate',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'event_type' => 'admission_interview_scheduled',
                'notification_type' => 'both',
                'is_enabled' => true,
                'template_subject' => 'Admission interview scheduled',
                'template_body' => 'Dear {{parent_name}}, an admission interview for {{candidate_name}} has been scheduled on {{interview_date}}.',
                'recipients' => json_encode(['parents']),
                'conditions' => null,
                'schedule_type' => 'immediate',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'event_type' => 'admission_confirmed',
                'notification_type' => 'both',
                'is_enabled' => true,
                'template_subject' => 'Admission confirmed',
                'template_body' => 'Dear {{parent_name}}, congratulations! {{candidate_name}} has been selected for admission. Our team will contact you with next steps.',
                'recipients' => json_encode(['parents']),
                'conditions' => null,
                'schedule_type' => 'immediate',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'event_type' => 'admission_rejected',
                'notification_type' => 'both',
                'is_enabled' => true,
                'template_subject' => 'Update on your admission enquiry',
                'template_body' => 'Dear {{parent_name}}, thank you for your interest. We are unable to proceed with the admission of {{candidate_name}} at this time.',
                'recipients' => json_encode(['parents']),
                'conditions' => null,
                'schedule_type' => 'immediate',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'event_type' => 'admission_admitted',
                'notification_type' => 'both',
                'is_enabled' => true,
                'template_subject' => 'Welcome! Admission confirmed and parent portal access',
                'template_body' => 'Dear {{parent_name}}, {{candidate_name}} has been admitted. Admission No: {{admission_no}}. Parent portal login: {{login}} / Temporary password: {{temp_password}} (you will be asked to set your own password on first login).',
                'recipients' => json_encode(['parents']),
                'conditions' => null,
                'schedule_type' => 'immediate',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'event_type' => 'admission_fee_received',
                'notification_type' => 'both',
                'is_enabled' => true,
                'template_subject' => 'Payment received',
                'template_body' => 'Dear {{parent_name}}, we have received a payment of {{amount}} ({{payment_mode}}) for {{candidate_name}}. Receipt No: {{receipt_no}}.',
                'recipients' => json_encode(['parents']),
                'conditions' => null,
                'schedule_type' => 'immediate',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];
        
        // Insert notifications if they don't exist
        foreach ($notifications as $notification) {
            $exists = DB::table('notification_settings')
                ->where('event_type', $notification['event_type'])
                ->exists();
                
            if (!$exists) {
                DB::table('notification_settings')->insert($notification);
            }
        }
        
        $this->command->info('Notification settings seeded successfully.');
    }
}
