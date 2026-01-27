<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BiometricSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_start_time',
        'school_end_time',
        'grace_period_minutes',
        'lunch_break_duration',
        'break_time_duration',
        'half_day_minimum_hours',
        'late_tolerance_limit',
        'early_departure_tolerance',
        'exclude_lunch_from_working_hours',
        'exclude_breaks_from_working_hours',
        'is_active',
        'description',
    ];

    protected $casts = [
        'school_start_time' => 'datetime:H:i:s',
        'school_end_time' => 'datetime:H:i:s',
        'grace_period_minutes' => 'integer',
        'lunch_break_duration' => 'integer',
        'break_time_duration' => 'integer',
        'half_day_minimum_hours' => 'decimal:2',
        'late_tolerance_limit' => 'integer',
        'early_departure_tolerance' => 'integer',
        'exclude_lunch_from_working_hours' => 'boolean',
        'exclude_breaks_from_working_hours' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Ensure only one active setting
    public static function boot()
    {
        parent::boot();
        
        static::saving(function ($model) {
            if ($model->is_active) {
                // Deactivate all other settings
                self::where('id', '!=', $model->id)->update(['is_active' => false]);
            }
            // Clear cache when settings change
            Cache::forget('active_biometric_settings');
        });
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Accessors
    public function getSchoolStartTimeFormattedAttribute()
    {
        return $this->school_start_time ? $this->school_start_time->format('h:i A') : 'N/A';
    }

    public function getSchoolEndTimeFormattedAttribute()
    {
        return $this->school_end_time ? $this->school_end_time->format('h:i A') : 'N/A';
    }

    public function getTotalWorkingHoursAttribute()
    {
        if (!$this->school_start_time || !$this->school_end_time) {
            return 0;
        }
        
        $start = $this->school_start_time;
        $end = $this->school_end_time;
        
        $totalMinutes = ($end->hour * 60 + $end->minute) - ($start->hour * 60 + $start->minute);
        
        // Subtract breaks if excluded
        if ($this->exclude_lunch_from_working_hours) {
            $totalMinutes -= $this->lunch_break_duration;
        }
        
        if ($this->exclude_breaks_from_working_hours) {
            $totalMinutes -= $this->break_time_duration;
        }
        
        return max(0, $totalMinutes / 60);
    }

    // Static Methods
    public static function getActiveSettings()
    {
        return Cache::remember('active_biometric_settings', 3600, function () {
            return self::where('is_active', true)->first();
        });
    }

    public static function getDefaultSettings()
    {
        return [
            'school_start_time' => '08:00:00',
            'school_end_time' => '16:00:00',
            'grace_period_minutes' => 15,
            'lunch_break_duration' => 60,
            'break_time_duration' => 30,
            'half_day_minimum_hours' => 4.00,
            'late_tolerance_limit' => 30,
            'early_departure_tolerance' => 30,
            'exclude_lunch_from_working_hours' => true,
            'exclude_breaks_from_working_hours' => true,
        ];
    }

    // Validation Rules
    public static function getValidationRules($exceptId = null)
    {
        $rules = [
            'school_start_time' => 'required|date_format:H:i:s',
            'school_end_time' => 'required|date_format:H:i:s|after:school_start_time',
            'grace_period_minutes' => 'required|integer|min:0|max:120',
            'lunch_break_duration' => 'required|integer|min:0|max:240',
            'break_time_duration' => 'required|integer|min:0|max:120',
            'half_day_minimum_hours' => 'required|numeric|min:1|max:12',
            'late_tolerance_limit' => 'required|integer|min:0|max:120',
            'early_departure_tolerance' => 'required|integer|min:0|max:120',
            'exclude_lunch_from_working_hours' => 'boolean',
            'exclude_breaks_from_working_hours' => 'boolean',
            'description' => 'nullable|string|max:500',
        ];
        
        if ($exceptId) {
            $rules['is_active'] = 'boolean';
        } else {
            $rules['is_active'] = 'boolean';
        }
        
        return $rules;
    }
}
