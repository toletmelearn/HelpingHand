<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Subject;
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

        // class_id/subject_id are NOT NULL on exams (see 2026_08_29_120200)
        // -- resolved here the same way the one-time backfill migrations
        // did, by exact name match, rather than leaving them out.
        $class10 = SchoolClass::firstOrCreate(['name' => 'Class 10']);
        $class9 = SchoolClass::firstOrCreate(['name' => 'Class 9']);
        $mathematics = Subject::firstOrCreate(['name' => 'Mathematics']);
        $science = Subject::firstOrCreate(['name' => 'Science']);
        $english = Subject::firstOrCreate(['name' => 'English']);

        // Create sample exams
        Exam::create([
            'name' => 'Mid Term Exam',
            'exam_type' => 'mid-term',
            'class_id' => $class10->id,
            'class_name' => $class10->name,
            'subject_id' => $mathematics->id,
            'subject' => $mathematics->name,
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
            'class_id' => $class10->id,
            'class_name' => $class10->name,
            'subject_id' => $science->id,
            'subject' => $science->name,
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
            'class_id' => $class9->id,
            'class_name' => $class9->name,
            'subject_id' => $english->id,
            'subject' => $english->name,
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
