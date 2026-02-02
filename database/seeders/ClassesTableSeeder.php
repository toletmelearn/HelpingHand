<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClassesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classes = [
            ['name' => 'Nursery', 'class_order' => 1, 'description' => 'Pre-primary education'],
            ['name' => 'LKG', 'class_order' => 2, 'description' => 'Lower Kindergarten'],
            ['name' => 'UKG', 'class_order' => 3, 'description' => 'Upper Kindergarten'],
            ['name' => 'Class 1', 'class_order' => 4, 'description' => 'Primary education - Grade 1'],
            ['name' => 'Class 2', 'class_order' => 5, 'description' => 'Primary education - Grade 2'],
            ['name' => 'Class 3', 'class_order' => 6, 'description' => 'Primary education - Grade 3'],
            ['name' => 'Class 4', 'class_order' => 7, 'description' => 'Primary education - Grade 4'],
            ['name' => 'Class 5', 'class_order' => 8, 'description' => 'Primary education - Grade 5'],
            ['name' => 'Class 6', 'class_order' => 9, 'description' => 'Middle school - Grade 6'],
            ['name' => 'Class 7', 'class_order' => 10, 'description' => 'Middle school - Grade 7'],
            ['name' => 'Class 8', 'class_order' => 11, 'description' => 'Middle school - Grade 8'],
            ['name' => 'Class 9', 'class_order' => 12, 'description' => 'Secondary school - Grade 9'],
            ['name' => 'Class 10', 'class_order' => 13, 'description' => 'Secondary school - Grade 10'],
            ['name' => 'Class 11 Science', 'class_order' => 20, 'description' => 'Higher Secondary - Grade 11 (Science Stream)'],
            ['name' => 'Class 11 Commerce', 'class_order' => 21, 'description' => 'Higher Secondary - Grade 11 (Commerce Stream)'],
            ['name' => 'Class 11 Arts', 'class_order' => 22, 'description' => 'Higher Secondary - Grade 11 (Arts Stream)'],
            ['name' => 'Class 12 Science', 'class_order' => 23, 'description' => 'Higher Secondary - Grade 12 (Science Stream)'],
            ['name' => 'Class 12 Commerce', 'class_order' => 24, 'description' => 'Higher Secondary - Grade 12 (Commerce Stream)'],
            ['name' => 'Class 12 Arts', 'class_order' => 25, 'description' => 'Higher Secondary - Grade 12 (Arts Stream)'],
        ];

        foreach ($classes as $class) {
            \App\Models\SchoolClass::updateOrCreate(
                ['name' => $class['name']],
                $class
            );
        }
    }
}
