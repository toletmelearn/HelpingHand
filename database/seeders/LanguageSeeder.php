<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Language;
use App\Models\Translation;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default languages
        $languages = [
            [
                'name' => 'English',
                'code' => 'en',
                'flag_icon' => '🇬🇧',
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Hindi',
                'code' => 'hi',
                'flag_icon' => '🇮🇳',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 2,
            ],
        ];
        
        foreach ($languages as $languageData) {
            $language = Language::create($languageData);
            
            // Add some sample translations for English (default)
            if ($language->code === 'en') {
                $this->createSampleTranslations($language);
            }
        }
    }
    
    private function createSampleTranslations($language)
    {
        $translations = [
            // General translations
            ['key' => 'dashboard', 'value' => 'Dashboard', 'module' => 'general'],
            ['key' => 'students', 'value' => 'Students', 'module' => 'general'],
            ['key' => 'teachers', 'value' => 'Teachers', 'module' => 'general'],
            ['key' => 'attendance', 'value' => 'Attendance', 'module' => 'general'],
            ['key' => 'fees', 'value' => 'Fees', 'module' => 'general'],
            ['key' => 'exams', 'value' => 'Exams', 'module' => 'general'],
            ['key' => 'reports', 'value' => 'Reports', 'module' => 'general'],
            ['key' => 'settings', 'value' => 'Settings', 'module' => 'general'],
            
            // Admin specific translations
            ['key' => 'admin_dashboard', 'value' => 'Admin Dashboard', 'module' => 'admin'],
            ['key' => 'manage_students', 'value' => 'Manage Students', 'module' => 'admin'],
            ['key' => 'manage_teachers', 'value' => 'Manage Teachers', 'module' => 'admin'],
            ['key' => 'fee_management', 'value' => 'Fee Management', 'module' => 'admin'],
            ['key' => 'exam_schedules', 'value' => 'Exam Schedules', 'module' => 'admin'],
            
            // Student specific translations
            ['key' => 'student_dashboard', 'value' => 'Student Dashboard', 'module' => 'student'],
            ['key' => 'my_attendance', 'value' => 'My Attendance', 'module' => 'student'],
            ['key' => 'my_results', 'value' => 'My Results', 'module' => 'student'],
            ['key' => 'timetable', 'value' => 'Timetable', 'module' => 'student'],
        ];
        
        foreach ($translations as $transData) {
            Translation::create([
                'language_id' => $language->id,
                'key' => $transData['key'],
                'value' => $transData['value'],
                'module' => $transData['module'],
                'is_published' => true,
            ]);
        }
    }
}