<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BiometricSetting;

class BiometricSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Check if biometric settings already exist
        if (BiometricSetting::count() > 0) {
            $this->command->info('Biometric settings already exist. Skipping seeding.');
            return;
        }

        // Create default biometric settings
        BiometricSetting::create([
            'school_start_time' => '08:00:00',
            'school_end_time' => '16:00:00',
            'grace_period_minutes' => 15,
            'lunch_break_duration' => 60,
            'break_time_duration' => 15,
            'half_day_minimum_hours' => 4.00,
            'late_tolerance_limit' => 30,
            'early_departure_tolerance' => 30,
            'exclude_lunch_from_working_hours' => true,
            'exclude_breaks_from_working_hours' => true,
            'enable_late_arrival_notifications' => true,
            'enable_early_departure_notifications' => true,
            'enable_half_day_notifications' => true,
            'enable_performance_notifications' => true,
            'notification_preferences' => [],
            'is_active' => true,
            'description' => 'Default working hours configuration for school staff',
        ]);

        $this->command->info('Biometric settings seeded successfully.');
    }
}