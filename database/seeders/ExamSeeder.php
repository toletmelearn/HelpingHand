<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Database\Seeder;

class ExamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@school.com')->first();
        
        // Create sample exams
        Exam::create([
            'name' => 'Mid Term Exam',
            'exam_type' => 'mid-term',
            'class_name' => 'Class 10',
            'subject' => 'Mathematics',
            'exam_date' => today()->addDays(10),
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'total_marks' => 100,
            'passing_marks' => 35,
            'description' => 'Mid Term Examination for Class 10 Mathematics',
            'academic_year' => '2025-2026',
            'term' => 'Semester 1',
            'status' => 'scheduled',
            'created_by' => $admin ? $admin->id : null,
        ]);
        
        Exam::create([
            'name' => 'Final Term Exam',
            'exam_type' => 'final',
            'class_name' => 'Class 10',
            'subject' => 'Science',
            'exam_date' => today()->addDays(20),
            'start_time' => '10:00:00',
            'end_time' => '13:00:00',
            'total_marks' => 100,
            'passing_marks' => 35,
            'description' => 'Final Term Examination for Class 10 Science',
            'academic_year' => '2025-2026',
            'term' => 'Semester 1',
            'status' => 'scheduled',
            'created_by' => $admin ? $admin->id : null,
        ]);
        
        Exam::create([
            'name' => 'Unit Test 1',
            'exam_type' => 'unit-test',
            'class_name' => 'Class 9',
            'subject' => 'English',
            'exam_date' => today()->addDays(5),
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
            'total_marks' => 50,
            'passing_marks' => 18,
            'description' => 'Unit Test 1 for Class 9 English',
            'academic_year' => '2025-2026',
            'term' => 'Quarter 1',
            'status' => 'scheduled',
            'created_by' => $admin ? $admin->id : null,
        ]);
        
        $this->command->info('Exams created successfully!');
    }
}
