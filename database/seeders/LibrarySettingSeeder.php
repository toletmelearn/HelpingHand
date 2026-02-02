<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LibrarySetting;

class LibrarySettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        LibrarySetting::firstOrCreate([], [
            'default_issue_days' => 14,
            'fine_per_day' => 1.00,
            'low_stock_threshold' => 5,
            'auto_reminder_enabled' => true,
        ]);
    }
}
