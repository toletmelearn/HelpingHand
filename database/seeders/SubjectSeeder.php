<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subject;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subjects = [
            // Scholastic Subjects
            [
                'name' => 'English',
                'code' => 'ENG',
                'subject_type' => 'scholastic',
                'is_active' => true,
                'sort_order' => 1
            ],
            [
                'name' => 'Mathematics',
                'code' => 'MATH',
                'subject_type' => 'scholastic',
                'is_active' => true,
                'sort_order' => 2
            ],
            [
                'name' => 'Science',
                'code' => 'SCI',
                'subject_type' => 'scholastic',
                'is_active' => true,
                'sort_order' => 3
            ],
            [
                'name' => 'Social Science',
                'code' => 'SST',
                'subject_type' => 'scholastic',
                'is_active' => true,
                'sort_order' => 4
            ],
            [
                'name' => 'Hindi',
                'code' => 'HIN',
                'subject_type' => 'scholastic',
                'is_active' => true,
                'sort_order' => 5
            ],
            [
                'name' => 'Sanskrit',
                'code' => 'SAN',
                'subject_type' => 'scholastic',
                'is_active' => true,
                'sort_order' => 6
            ],
            [
                'name' => 'Computer Science',
                'code' => 'CS',
                'subject_type' => 'scholastic',
                'is_active' => true,
                'sort_order' => 7
            ],
            [
                'name' => 'Physical Education',
                'code' => 'PE',
                'subject_type' => 'scholastic',
                'is_active' => true,
                'sort_order' => 8
            ],

            // Co-Scholastic Subjects
            [
                'name' => 'Work Education',
                'code' => 'WE',
                'subject_type' => 'co_scholastic',
                'is_active' => true,
                'sort_order' => 9
            ],
            [
                'name' => 'Art Education',
                'code' => 'AE',
                'subject_type' => 'co_scholastic',
                'is_active' => true,
                'sort_order' => 10
            ],
            [
                'name' => 'Health & Physical Education',
                'code' => 'HPE',
                'subject_type' => 'co_scholastic',
                'is_active' => true,
                'sort_order' => 11
            ],
            [
                'name' => 'Discipline',
                'code' => 'DIS',
                'subject_type' => 'co_scholastic',
                'is_active' => true,
                'sort_order' => 12
            ],
            [
                'name' => 'Regular & Promptness',
                'code' => 'RP',
                'subject_type' => 'co_scholastic',
                'is_active' => true,
                'sort_order' => 13
            ]
        ];

        foreach ($subjects as $subjectData) {
            Subject::updateOrCreate(
                ['code' => $subjectData['code']],
                $subjectData
            );
        }

        $this->command->info('Subjects seeded successfully!');
    }
}