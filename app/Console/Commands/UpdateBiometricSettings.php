<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BiometricSetting;

class UpdateBiometricSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-biometric-settings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update existing biometric settings with new notification fields';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $settings = BiometricSetting::all();
        
        foreach ($settings as $setting) {
            $setting->update([
                'enable_late_arrival_notifications' => true,
                'enable_early_departure_notifications' => true,
                'enable_half_day_notifications' => true,
                'enable_performance_notifications' => true,
                'notification_preferences' => [],
            ]);
            
            $this->info("Updated biometric setting ID: " . $setting->id);
        }
        
        if ($settings->isEmpty()) {
            $this->info('No biometric settings found to update.');
            
            // Create default settings if none exist
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
            
            $this->info('Created default biometric settings.');
        } else {
            $this->info('Updated ' . $settings->count() . ' biometric setting(s).');
        }

        return Command::SUCCESS;
    }
}