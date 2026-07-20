<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Teacher;
use App\Models\TeacherLogin;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\ExamHead;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;

class TeacherLoginSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some teachers (assuming they already exist)
        $teachers = Teacher::limit(5)->get();
        
        if ($teachers->isEmpty()) {
            $this->command->warn('No teachers found in database. Please add teachers first.');
            return;
        }

        $defaultPassword = Hash::make('123456');

        // Create teacher logins
        foreach ($teachers as $index => $teacher) {
            // Create username from mobile or employee_id
            $username = $teacher->phone ?? $teacher->employee_id ?? '9876543' . str_pad($index + 10, 2, '0', STR_PAD_LEFT);
            
            TeacherLogin::updateOrCreate(
                ['teacher_id' => $teacher->id],
                [
                    'school_id' => null, // Set to your school_id if exists
                    'username' => $username,
                    'password' => $defaultPassword,
                    'status' => 'active',
                    'force_password_change' => true, // Force password change on first login
                ]
            );

            $this->command->info("Created login for {$teacher->name} - Username: {$username} | Password: 123456");
        }

        // Create some teacher assignments
        $classes = SchoolClass::limit(3)->get();
        $subjects = Subject::limit(5)->get();

        if ($classes->isNotEmpty() && $subjects->isNotEmpty()) {
            foreach ($teachers as $index => $teacher) {
                // Assign 2-3 subjects to each teacher
                $assignedSubjects = $subjects->random(min(2, $subjects->count()));
                
                foreach ($assignedSubjects as $subject) {
                    $class = $classes->random();
                    
                    TeacherClassSubjectAssignment::updateOrCreate(
                        [
                            'teacher_id' => $teacher->id,
                            'class_id' => $class->id,
                            'subject_id' => $subject->id,
                        ],
                        [
                            'school_id' => null,
                            'section_id' => null,
                            'is_class_teacher' => $index === 0, // Make first teacher a class teacher
                            'academic_year' => '2025-2026',
                        ]
                    );
                }
            }

            $this->command->info('Created teacher class/subject assignments');
        }

        // Assign first teacher as exam head
        if ($teachers->isNotEmpty()) {
            $adminUser = User::first();
            
            if ($adminUser) {
                ExamHead::updateOrCreate(
                    ['teacher_id' => $teachers->first()->id],
                    [
                        'school_id' => null,
                        'assigned_by' => $adminUser->id,
                        'assigned_at' => now(),
                        'status' => 'active',
                    ]
                );

                $this->command->info("Assigned {$teachers->first()->name} as Exam Head");
            }
        }

        $this->command->info('========================================');
        $this->command->info('Teacher Login Credentials Created!');
        $this->command->info('========================================');
        $this->command->info('Login URL: /teacher/login');
        $this->command->info('Default Password for all: 123456');
        $this->command->info('IMPORTANT: Teachers will be forced to change password on first login');
        $this->command->info('========================================');
    }
}
