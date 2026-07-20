<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LanguageSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if the table exists
        if (!Schema::hasTable('language_settings')) {
            $this->command->info('Language settings table does not exist. Please run migrations first.');
            return;
        }
        
        // Get a user ID for created_by and updated_by fields
        $user = DB::table('users')->first();
        $userId = $user ? $user->id : null;
        
        // Define default language settings
        $languages = [
            [
                'locale' => 'en',
                'name' => 'English',
                'flag' => '🇬🇧',
                'is_default' => true,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'locale' => 'es',
                'name' => 'Spanish',
                'flag' => '🇪🇸',
                'is_default' => false,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'locale' => 'fr',
                'name' => 'French',
                'flag' => '🇫🇷',
                'is_default' => false,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'locale' => 'de',
                'name' => 'German',
                'flag' => '🇩🇪',
                'is_default' => false,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'locale' => 'hi',
                'name' => 'Hindi',
                'flag' => '🇮🇳',
                'is_default' => false,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];
        
        // Insert languages if they don't exist
        foreach ($languages as $language) {
            $exists = DB::table('language_settings')
                ->where('locale', $language['locale'])
                ->exists();
                
            if (!$exists) {
                DB::table('language_settings')->insert($language);
            }
        }
        
        $this->command->info('Language settings seeded successfully.');
    }
}
