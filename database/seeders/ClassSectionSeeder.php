<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ClassManagement;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

class ClassSectionSeeder extends Seeder
{
    public function run(): void
    {
        // Create class management entries
        $this->seedClassManagement();
        
        // Create sections
        $this->seedSections();
        
        // Assign sections to classes
        $this->assignSectionsToClasses();
        
        echo "Classes and sections seeded successfully!\n";
    }
    
    private function seedClassManagement(): void
    {
        $classes = [
            ['name' => 'Nursery', 'order' => 1, 'section' => '', 'stream' => '', 'capacity' => 25, 'description' => 'Pre-primary education', 'is_active' => 1],
            ['name' => 'LKG', 'order' => 2, 'section' => '', 'stream' => '', 'capacity' => 25, 'description' => 'Lower Kindergarten', 'is_active' => 1],
            ['name' => 'UKG', 'order' => 3, 'section' => '', 'stream' => '', 'capacity' => 25, 'description' => 'Upper Kindergarten', 'is_active' => 1],
            ['name' => 'Class 1', 'order' => 4, 'section' => '', 'stream' => '', 'capacity' => 35, 'description' => 'Primary education - Grade 1', 'is_active' => 1],
            ['name' => 'Class 2', 'order' => 5, 'section' => '', 'stream' => '', 'capacity' => 35, 'description' => 'Primary education - Grade 2', 'is_active' => 1],
            ['name' => 'Class 3', 'order' => 6, 'section' => '', 'stream' => '', 'capacity' => 35, 'description' => 'Primary education - Grade 3', 'is_active' => 1],
            ['name' => 'Class 4', 'order' => 7, 'section' => '', 'stream' => '', 'capacity' => 35, 'description' => 'Primary education - Grade 4', 'is_active' => 1],
            ['name' => 'Class 5', 'order' => 8, 'section' => '', 'stream' => '', 'capacity' => 35, 'description' => 'Primary education - Grade 5', 'is_active' => 1],
            ['name' => 'Class 6', 'order' => 9, 'section' => '', 'stream' => '', 'capacity' => 40, 'description' => 'Middle school - Grade 6', 'is_active' => 1],
            ['name' => 'Class 7', 'order' => 10, 'section' => '', 'stream' => '', 'capacity' => 40, 'description' => 'Middle school - Grade 7', 'is_active' => 1],
            ['name' => 'Class 8', 'order' => 11, 'section' => '', 'stream' => '', 'capacity' => 40, 'description' => 'Middle school - Grade 8', 'is_active' => 1],
            ['name' => 'Class 9', 'order' => 12, 'section' => '', 'stream' => '', 'capacity' => 40, 'description' => 'Secondary school - Grade 9', 'is_active' => 1],
            ['name' => 'Class 10', 'order' => 13, 'section' => '', 'stream' => '', 'capacity' => 40, 'description' => 'Secondary school - Grade 10', 'is_active' => 1],
            ['name' => 'Class 11', 'order' => 14, 'section' => 'Science', 'stream' => 'Science', 'capacity' => 30, 'description' => 'Higher Secondary - Grade 11 (Science Stream)', 'is_active' => 1],
            ['name' => 'Class 11', 'order' => 15, 'section' => 'Commerce', 'stream' => 'Commerce', 'capacity' => 30, 'description' => 'Higher Secondary - Grade 11 (Commerce Stream)', 'is_active' => 1],
            ['name' => 'Class 11', 'order' => 16, 'section' => 'Arts', 'stream' => 'Arts', 'capacity' => 30, 'description' => 'Higher Secondary - Grade 11 (Arts Stream)', 'is_active' => 1],
            ['name' => 'Class 12', 'order' => 17, 'section' => 'Science', 'stream' => 'Science', 'capacity' => 30, 'description' => 'Higher Secondary - Grade 12 (Science Stream)', 'is_active' => 1],
            ['name' => 'Class 12', 'order' => 18, 'section' => 'Commerce', 'stream' => 'Commerce', 'capacity' => 30, 'description' => 'Higher Secondary - Grade 12 (Commerce Stream)', 'is_active' => 1],
            ['name' => 'Class 12', 'order' => 19, 'section' => 'Arts', 'stream' => 'Arts', 'capacity' => 30, 'description' => 'Higher Secondary - Grade 12 (Arts Stream)', 'is_active' => 1],
        ];

        foreach ($classes as $class) {
            $existing = ClassManagement::where('name', $class['name'])
                ->where('section', $class['section'])
                ->where('stream', $class['stream'])
                ->first();
            
            if ($existing) {
                // Update existing record with order value
                $existing->update(['order' => $class['order']]);
            } else {
                // Create new record
                ClassManagement::create($class);
            }
        }
    }
    
    private function seedSections(): void
    {
        // Common section names
        $sectionNames = ['A', 'B', 'C', 'D'];
        
        foreach ($sectionNames as $sectionName) {
            DB::table('sections')->updateOrInsert(
                ['name' => $sectionName],
                [
                    'name' => $sectionName,
                    'capacity' => 40,
                    'description' => "Section {$sectionName}",
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }
    }
    
    private function assignSectionsToClasses(): void
    {
        $classes = ClassManagement::all();
        $sections = DB::table('sections')->get();
        
        foreach ($classes as $class) {
            // Assign sections based on class type
            $sectionsToAssign = $this->getSectionsForClass($class, $sections);
            
            foreach ($sectionsToAssign as $section) {
                // Create class-section assignment
                DB::table('class_sections')->updateOrInsert(
                    [
                        'class_management_id' => $class->id,
                        'section_id' => $section->id
                    ],
                    [
                        'assigned_at' => now()
                    ]
                );
            }
        }
    }
    
    private function getSectionsForClass($class, $sections)
    {
        // Convert to collection and filter
        $sectionsCollection = collect($sections);
        
        // Nursery, LKG, UKG: sections A and B
        if (in_array($class->name, ['Nursery', 'LKG', 'UKG'])) {
            return $sectionsCollection->filter(function ($section) {
                return in_array($section->name, ['A', 'B']);
            });
        }
        
        // Classes 1-5: sections A, B, and C
        if (in_array($class->name, ['Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5'])) {
            return $sectionsCollection->filter(function ($section) {
                return in_array($section->name, ['A', 'B', 'C']);
            });
        }
        
        // Classes 6-10: all sections A, B, C, D
        if (in_array($class->name, ['Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10'])) {
            return $sectionsCollection;
        }
        
        // Classes 11-12: only section A (since they're stream-specific)
        if (in_array($class->name, ['Class 11', 'Class 12'])) {
            return $sectionsCollection->filter(function ($section) {
                return $section->name === 'A';
            });
        }
        
        return $sectionsCollection->filter(function ($section) {
            return $section->name === 'A';
        });
    }
}