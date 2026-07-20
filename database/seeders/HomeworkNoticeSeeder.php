<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\HomeworkNotice;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;

class HomeworkNoticeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some existing classes, subjects, and users for seeding
        $classes = SchoolClass::limit(5)->get();
        $subjects = Subject::limit(5)->get();
        $users = User::role('teacher')->limit(3)->get();
        
        if ($classes->isEmpty() || $users->isEmpty()) {
            // If no classes or users exist, create basic ones for demo
            $class = SchoolClass::create([
                'name' => 'Class 1',
                'class_order' => 1,
                'description' => 'First Class',
                'is_active' => true
            ]);
            
            $user = User::create([
                'name' => 'Demo Teacher',
                'email' => 'demo.teacher@example.com',
                'password' => bcrypt('password'),
                'role' => 'teacher',
                'status' => 'active'
            ]);
            
            $classes = collect([$class]);
            $users = collect([$user]);
        }
        
        $homeworkNotices = [
            [
                'title' => 'Mathematics Assignment',
                'description' => 'Complete exercises 1-10 from chapter 5 on algebra.',
                'type' => 'homework',
                'class_id' => $classes->first()->id,
                'subject_id' => $subjects->isNotEmpty() ? $subjects->first()->id : null,
                'assigned_by' => $users->first()->id,
                'due_date' => now()->addDays(7),
                'publish_date' => now(),
                'status' => 'active',
                'priority' => 'medium'
            ],
            [
                'title' => 'Science Project Submission',
                'description' => 'Submit your science project on renewable energy sources. Include research, diagrams, and presentation slides.',
                'type' => 'homework',
                'class_id' => $classes->first()->id,
                'subject_id' => $subjects->isNotEmpty() ? $subjects->skip(1)->first()?->id : null,
                'assigned_by' => $users->first()->id,
                'due_date' => now()->addDays(14),
                'publish_date' => now(),
                'status' => 'active',
                'priority' => 'high'
            ],
            [
                'title' => 'School Holiday Notice',
                'description' => 'School will remain closed on Monday, February 17th, 2026, for Presidents Day holiday. Classes resume on Tuesday.',
                'type' => 'notice',
                'class_id' => $classes->first()->id,
                'subject_id' => null,
                'assigned_by' => $users->first()->id,
                'due_date' => null,
                'publish_date' => now(),
                'status' => 'published',
                'priority' => 'high'
            ],
            [
                'title' => 'Parent-Teacher Meeting',
                'description' => 'Quarterly Parent-Teacher meeting scheduled for March 5th, 2026. Please confirm your attendance by replying to this notice.',
                'type' => 'announcement',
                'class_id' => $classes->first()->id,
                'subject_id' => null,
                'assigned_by' => $users->first()->id,
                'due_date' => now()->addDays(10),
                'publish_date' => now(),
                'status' => 'active',
                'priority' => 'high'
            ],
            [
                'title' => 'English Essay Topic',
                'description' => 'Write an essay on "Importance of Education in Modern Times". Word limit: 500-800 words. Submit by end of week.',
                'type' => 'homework',
                'class_id' => $classes->first()->id,
                'subject_id' => $subjects->isNotEmpty() ? $subjects->skip(2)->first()?->id : null,
                'assigned_by' => $users->first()->id,
                'due_date' => now()->addDays(5),
                'publish_date' => now(),
                'status' => 'active',
                'priority' => 'medium'
            ]
        ];
        
        foreach ($homeworkNotices as $noticeData) {
            HomeworkNotice::create($noticeData);
        }
    }
}