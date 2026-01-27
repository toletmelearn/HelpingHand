<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class WorkingHoursConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'exclude_lunch_break',
        'exclude_other_breaks',
        'calculation_method',
        'is_default',
        'custom_breaks',
    ];

    protected $casts = [
        'exclude_lunch_break' => 'boolean',
        'exclude_other_breaks' => 'boolean',
        'is_default' => 'boolean',
        'custom_breaks' => 'array',
    ];

    // Ensure only one default configuration
    public static function boot()
    {
        parent::boot();
        
        static::saving(function ($model) {
            if ($model->is_default) {
                // Remove default from other configurations
                self::where('id', '!=', $model->id)->update(['is_default' => false]);
            }
            // Clear cache when configurations change
            Cache::forget('default_working_hours_config');
        });
    }

    // Scopes
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeNetDuration($query)
    {
        return $query->where('calculation_method', 'net_duration');
    }

    public function scopeGrossDuration($query)
    {
        return $query->where('calculation_method', 'gross_duration');
    }

    // Accessors
    public function getCalculationMethodLabelAttribute()
    {
        return $this->calculation_method === 'net_duration' 
            ? 'Net Duration (Exclude Breaks)' 
            : 'Gross Duration (Include All Time)';
    }

    // Static Methods
    public static function getDefaultConfiguration()
    {
        return Cache::remember('default_working_hours_config', 3600, function () {
            return self::where('is_default', true)->first() 
                ?? self::createDefaultConfiguration();
        });
    }

    public static function createDefaultConfiguration()
    {
        return self::create([
            'name' => 'Standard Working Hours',
            'description' => 'Default configuration excluding lunch and break times',
            'exclude_lunch_break' => true,
            'exclude_other_breaks' => true,
            'calculation_method' => 'net_duration',
            'is_default' => true,
            'custom_breaks' => [],
        ]);
    }

    // Calculation Methods
    public function calculateNetDuration($startTime, $endTime, $breakDurations = [])
    {
        if (!$startTime || !$endTime) {
            return 0;
        }
        
        $start = strtotime($startTime);
        $end = strtotime($endTime);
        
        if ($start >= $end) {
            return 0;
        }
        
        $totalSeconds = $end - $start;
        
        // Subtract configured breaks
        if ($this->exclude_lunch_break) {
            $totalSeconds -= 60 * 60; // 1 hour lunch
        }
        
        if ($this->exclude_other_breaks) {
            $totalSeconds -= 30 * 60; // 30 minutes other breaks
        }
        
        // Subtract custom breaks
        foreach ($breakDurations as $duration) {
            $totalSeconds -= $duration * 60; // Convert minutes to seconds
        }
        
        return max(0, $totalSeconds / 3600); // Convert to hours
    }

    public function calculateGrossDuration($startTime, $endTime)
    {
        if (!$startTime || !$endTime) {
            return 0;
        }
        
        $start = strtotime($startTime);
        $end = strtotime($endTime);
        
        if ($start >= $end) {
            return 0;
        }
        
        return ($end - $start) / 3600; // Convert seconds to hours
    }

    // Validation Rules
    public static function getValidationRules($exceptId = null)
    {
        $rules = [
            'name' => 'required|string|max:100|unique:working_hours_configurations,name',
            'description' => 'nullable|string|max:500',
            'exclude_lunch_break' => 'boolean',
            'exclude_other_breaks' => 'boolean',
            'calculation_method' => 'required|in:net_duration,gross_duration',
            'custom_breaks' => 'nullable|array',
        ];
        
        if ($exceptId) {
            $rules['name'] = 'required|string|max:100|unique:working_hours_configurations,name,' . $exceptId;
        }
        
        return $rules;
    }
}
