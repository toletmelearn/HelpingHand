<?php

namespace App\Services;

use App\Models\BiometricSetting;
use App\Models\WorkingHoursConfiguration;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BiometricCalculationService
{
    protected $settings;
    protected $configuration;
    
    public function __construct()
    {
        $this->settings = BiometricSetting::getActiveSettings();
        $this->configuration = WorkingHoursConfiguration::getDefaultConfiguration();
    }
    
    /**
     * Calculate biometric data from raw input
     */
    public function calculateBiometricData($data)
    {
        if (!$this->settings) {
            $this->settings = new \App\Models\BiometricSetting(\App\Models\BiometricSetting::getDefaultSettings());
        }
        
        $result = [
            'calculated_duration' => 0,
            'arrival_status' => 'on_time',
            'departure_status' => 'on_time',
            'grace_minutes_used' => 0,
            'late_minutes' => 0,
            'early_departure_minutes' => 0,
        ];
        
        if (empty($data['first_in_time']) || empty($data['last_out_time'])) {
            return $result;
        }
        
        try {
            $firstIn = Carbon::createFromTimeString($data['first_in_time']);
            $lastOut = Carbon::createFromTimeString($data['last_out_time']);
            $schoolStart = Carbon::createFromTimeString($this->settings->school_start_time);
            $schoolEnd = Carbon::createFromTimeString($this->settings->school_end_time);
            
            // Calculate working duration
            $totalMinutes = $lastOut->diffInMinutes($firstIn);
            
            // Subtract breaks if configured
            if ($this->settings->exclude_lunch_from_working_hours) {
                $totalMinutes -= $this->settings->lunch_break_duration;
            }
            if ($this->settings->exclude_breaks_from_working_hours) {
                $totalMinutes -= $this->settings->break_time_duration;
            }
            
            $result['calculated_duration'] = max(0, $totalMinutes / 60);
            
            // Calculate arrival status
            $graceEndTime = $schoolStart->copy()->addMinutes($this->settings->grace_period_minutes);
            if ($firstIn->gt($graceEndTime)) {
                $result['arrival_status'] = 'late';
                $result['late_minutes'] = $firstIn->diffInMinutes($schoolStart);
                $result['grace_minutes_used'] = min($this->settings->grace_period_minutes, $result['late_minutes']);
            }
            
            // Calculate departure status
            $expectedEnd = $schoolEnd->copy()->subMinutes($this->settings->early_departure_tolerance);
            if ($lastOut->lt($expectedEnd)) {
                $result['departure_status'] = 'early_exit';
                $result['early_departure_minutes'] = $expectedEnd->diffInMinutes($lastOut);
            }
            
            // Check for half day
            if ($result['calculated_duration'] < $this->settings->half_day_minimum_hours) {
                $result['departure_status'] = 'half_day';
            }
            
        } catch (\Exception $e) {
            // Return default values if calculation fails
            Log::warning('Biometric calculation failed: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Calculate overtime hours
     */
    public function calculateOvertime($firstInTime, $lastOutTime)
    {
        if (!$firstInTime || !$lastOutTime || !$this->settings) {
            return 0;
        }
        
        $firstIn = Carbon::createFromTimeString($firstInTime);
        $lastOut = Carbon::createFromTimeString($lastOutTime);
        $schoolEnd = Carbon::createFromTimeString($this->settings->school_end_time);
        
        if ($lastOut->lte($schoolEnd)) {
            return 0;
        }
        
        return $lastOut->diffInHours($schoolEnd);
    }
    
    /**
     * Calculate undertime hours
     */
    public function calculateUndertime($firstInTime, $lastOutTime)
    {
        if (!$firstInTime || !$lastOutTime || !$this->settings) {
            return 0;
        }
        
        $firstIn = Carbon::createFromTimeString($firstInTime);
        $lastOut = Carbon::createFromTimeString($lastOutTime);
        $schoolStart = Carbon::createFromTimeString($this->settings->school_start_time);
        $schoolEnd = Carbon::createFromTimeString($this->settings->school_end_time);
        
        $expectedDuration = $schoolEnd->diffInHours($schoolStart);
        $actualDuration = $lastOut->diffInHours($firstIn);
        
        return max(0, $expectedDuration - $actualDuration);
    }
    
    /**
     * Get formatted working duration
     */
    public function formatWorkingDuration($hours)
    {
        if (!$hours) {
            return '0h 0m';
        }
        
        $wholeHours = floor($hours);
        $minutes = round(($hours - $wholeHours) * 60);
        
        return "{$wholeHours}h {$minutes}m";
    }
    
    /**
     * Get status badge information
     */
    public function getStatusBadgeInfo($arrivalStatus, $departureStatus)
    {
        $badgeClass = '';
        $text = '';
        
        if ($arrivalStatus === 'late') {
            $badgeClass = 'bg-warning';
            $text = 'Late Arrival';
        } elseif ($departureStatus === 'early_exit') {
            $badgeClass = 'bg-info';
            $text = 'Early Exit';
        } elseif ($departureStatus === 'half_day') {
            $badgeClass = 'bg-danger';
            $text = 'Half Day';
        } else {
            $badgeClass = 'bg-success';
            $text = 'On Time';
        }
        
        return [
            'class' => $badgeClass,
            'text' => $text
        ];
    }
    
    /**
     * Refresh settings from database
     */
    public function refreshSettings()
    {
        $this->settings = BiometricSetting::getActiveSettings();
        $this->configuration = WorkingHoursConfiguration::getDefaultConfiguration();
    }
}
