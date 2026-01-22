<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BellTiming extends Model
{
    use HasFactory;

    protected $fillable = [
        'day_of_week',      // Monday, Tuesday, etc.
        'period_name',      // Period 1, Period 2, Lunch, Break, etc.
        'start_time',       // HH:MM:SS format
        'end_time',         // HH:MM:SS format
        'class_section',    // Specific class/section if needed
        'is_active',        // Whether this schedule is currently active
        'is_break',         // Whether this is a break time
        'order_index',      // Order of periods in a day
        'academic_year',    // Academic year identifier
        'semester',         // Semester identifier
        'custom_label',     // Custom label for special periods
        'color_code',       // Color for calendar representation
        'created_by',       // User who created the schedule
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'is_active' => 'boolean',
        'is_break' => 'boolean',
        'order_index' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Days of the week constants
    const MONDAY = 'Monday';
    const TUESDAY = 'Tuesday';
    const WEDNESDAY = 'Wednesday';
    const THURSDAY = 'Thursday';
    const FRIDAY = 'Friday';
    const SATURDAY = 'Saturday';
    const SUNDAY = 'Sunday';

    // Period types
    const PERIOD_TYPE_REGULAR = 'regular';
    const PERIOD_TYPE_BREAK = 'break';
    const PERIOD_TYPE_LUNCH = 'lunch';
    const PERIOD_TYPE_ASSEMBLY = 'assembly';
    const PERIOD_TYPE_EXTRA_CURRICULAR = 'extra_curricular';

    // Scopes for common queries
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRegular($query)
    {
        return $query->where('is_break', false);
    }

    public function scopeBreaks($query)
    {
        return $query->where('is_break', true);
    }

    public function scopeByDay($query, $day)
    {
        return $query->where('day_of_week', $day);
    }

    public function scopeByClass($query, $classSection)
    {
        return $query->where('class_section', $classSection);
    }

    public function scopeByAcademicYear($query, $year)
    {
        return $query->where('academic_year', $year);
    }

    // Helper methods
    public function getDurationAttribute()
    {
        if ($this->start_time && $this->end_time) {
            $start = strtotime($this->start_time->format('H:i:s'));
            $end = strtotime($this->end_time->format('H:i:s'));
            
            if ($end >= $start) {
                $duration = $end - $start;
                $hours = floor($duration / 3600);
                $minutes = floor(($duration % 3600) / 60);
                return sprintf('%02d:%02d', $hours, $minutes);
            }
        }
        return '00:00';
    }

    public function getDayOrderAttribute()
    {
        $daysOrder = [
            self::MONDAY => 1,
            self::TUESDAY => 2,
            self::WEDNESDAY => 3,
            self::THURSDAY => 4,
            self::FRIDAY => 5,
            self::SATURDAY => 6,
            self::SUNDAY => 7
        ];
        
        return $daysOrder[$this->day_of_week] ?? 99;
    }

    public function isCurrentlyActive()
    {
        $now = now();
        $currentTime = $now->format('H:i:s');
        $currentDay = $now->format('l'); // Full day name
        
        return $this->day_of_week === $currentDay &&
               $currentTime >= $this->start_time->format('H:i:s') &&
               $currentTime <= $this->end_time->format('H:i:s') &&
               $this->is_active;
    }

    public static function getCurrentPeriod()
    {
        $now = now();
        $currentTime = $now->format('H:i:s');
        $currentDay = $now->format('l'); // Full day name
        
        return self::where('day_of_week', $currentDay)
                   ->where('start_time', '<=', $currentTime)
                   ->where('end_time', '>=', $currentTime)
                   ->where('is_active', true)
                   ->first();
    }

    public static function getTodaysSchedule($day = null, $classSection = null)
    {
        $day = $day ?: now()->format('l');
        
        $query = self::where('day_of_week', $day)
                     ->where('is_active', true)
                     ->orderBy('order_index');
        
        if ($classSection) {
            $query->where('class_section', $classSection);
        }
        
        return $query->get();
    }

    public static function getWeeklySchedule($classSection = null)
    {
        $query = self::where('is_active', true)
                     ->orderBy('getDayOrderAttribute')
                     ->orderBy('order_index');
        
        if ($classSection) {
            $query->where('class_section', $classSection);
        }
        
        return $query->get();
    }

    public static function getTimetableForClass($classSection, $academicYear = null)
    {
        $query = self::where('class_section', $classSection)
                     ->where('is_active', true);
        
        if ($academicYear) {
            $query->where('academic_year', $academicYear);
        }
        
        return $query->orderBy('getDayOrderAttribute')
                    ->orderBy('order_index')
                    ->get();
    }

    public function getFormattedTimeRange()
    {
        if ($this->start_time && $this->end_time) {
            return $this->start_time->format('h:i A') . ' - ' . $this->end_time->format('h:i A');
        }
        return 'Invalid Time';
    }

    public function getPeriodTypeAttribute()
    {
        if ($this->is_break) {
            if (stripos($this->period_name, 'lunch') !== false) {
                return self::PERIOD_TYPE_LUNCH;
            } elseif (stripos($this->period_name, 'break') !== false) {
                return self::PERIOD_TYPE_BREAK;
            } else {
                return self::PERIOD_TYPE_BREAK;
            }
        } elseif (stripos($this->period_name, 'assembly') !== false) {
            return self::PERIOD_TYPE_ASSEMBLY;
        } elseif (stripos($this->period_name, 'sports') !== false || stripos($this->period_name, 'games') !== false) {
            return self::PERIOD_TYPE_EXTRA_CURRICULAR;
        } else {
            return self::PERIOD_TYPE_REGULAR;
        }
    }
    
    /**
     * Get the user who created this bell timing.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}