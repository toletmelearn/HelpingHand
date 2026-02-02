<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SchoolClass;

class SchoolClassSeeder extends Seeder
{
    public function run(): void
    {
        $classes = [
            ['name' => 'Nursery', 'class_order' => 1, 'description' => 'Pre-primary education', 'is_active' => 1],
            ['name' => 'LKG', 'class_order' => 2, 'description' => 'Lower Kindergarten', 'is_active' => 1],
            ['name' => 'UKG', 'class_order' => 3, 'description' => 'Upper Kindergarten', 'is_active' => 1],
            ['name' => 'Class 1', 'class_order' => 4, 'description' => 'Primary education - Grade 1', 'is_active' => 1],
            ['name' => 'Class 2', 'class_order' => 5, 'description' => 'Primary education - Grade 2', 'is_active' => 1],
            ['name' => 'Class 3', 'class_order' => 6, 'description' => 'Primary education - Grade 3', 'is_active' => 1],
            ['name' => 'Class 4', 'class_order' => 7, 'description' => 'Primary education - Grade 4', 'is_active' => 1],
            ['name' => 'Class 5', 'class_order' => 8, 'description' => 'Primary education - Grade 5', 'is_active' => 1],
            ['name' => 'Class 6', 'class_order' => 9, 'description' => 'Middle school - Grade 6', 'is_active' => 1],
            ['name' => 'Class 7', 'class_order' => 10, 'description' => 'Middle school - Grade 7', 'is_active' => 1],
            ['name' => 'Class 8', 'class_order' => 11, 'description' => 'Middle school - Grade 8', 'is_active' => 1],
            ['name' => 'Class 9', 'class_order' => 12, 'description' => 'Secondary school - Grade 9', 'is_active' => 1],
            ['name' => 'Class 10', 'class_order' => 13, 'description' => 'Secondary school - Grade 10', 'is_active' => 1],
            ['name' => 'Class 11 Science', 'class_order' => 14, 'description' => 'Higher Secondary - Grade 11 (Science Stream)', 'is_active' => 1],
            ['name' => 'Class 11 Commerce', 'class_order' => 15, 'description' => 'Higher Secondary - Grade 11 (Commerce Stream)', 'is_active' => 1],
            ['name' => 'Class 11 Arts', 'class_order' => 16, 'description' => 'Higher Secondary - Grade 11 (Arts Stream)', 'is_active' => 1],
            ['name' => 'Class 12 Science', 'class_order' => 17, 'description' => 'Higher Secondary - Grade 12 (Science Stream)', 'is_active' => 1],
            ['name' => 'Class 12 Commerce', 'class_order' => 18, 'description' => 'Higher Secondary - Grade 12 (Commerce Stream)', 'is_active' => 1],
            ['name' => 'Class 12 Arts', 'class_order' => 19, 'description' => 'Higher Secondary - Grade 12 (Arts Stream)', 'is_active' => 1],
        ];

        foreach ($classes as $class) {
            SchoolClass::firstOrCreate(['name' => $class['name']], $class);
        }
        
        echo "School classes seeded successfully!\n";
    }
}
