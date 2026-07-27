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
        'period_type',      // teaching/assembly/prayer/break/zero/dispersal
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

    // Period types (T2b: real `period_type` column values, matching the
    // enum in the 2026_07_27_080014 migration). Replaces a same-named but
    // unused/never-called getPeriodTypeAttribute() guess-from-period_name
    // accessor that would otherwise have silently shadowed this real
    // column on every `$timing->period_type` access.
    const PERIOD_TYPE_TEACHING = 'teaching';
    const PERIOD_TYPE_ASSEMBLY = 'assembly';
    const PERIOD_TYPE_PRAYER = 'prayer';
    const PERIOD_TYPE_BREAK = 'break';
    const PERIOD_TYPE_ZERO = 'zero';
    const PERIOD_TYPE_DISPERSAL = 'dispersal';

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

    public function getDurationFormattedAttribute()
    {
        if ($this->start_time && $this->end_time) {
            $start = strtotime($this->start_time->format('H:i:s'));
            $end = strtotime($this->end_time->format('H:i:s'));
            
            if ($end >= $start) {
                $duration = $end - $start;
                $hours = floor($duration / 3600);
                $minutes = floor(($duration % 3600) / 60);
                
                if ($hours > 0) {
                    return $hours . 'h ' . $minutes . 'm';
                } else {
                    return $minutes . 'm';
                }
            }
        }
        return '0m';
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
        $query = self::where('is_active', true);
        
        if ($classSection) {
            $query->where('class_section', $classSection);
        }
        
        $results = $query->get();
        
        // Define day order mapping
        $daysOrder = [
            self::MONDAY => 1,
            self::TUESDAY => 2,
            self::WEDNESDAY => 3,
            self::THURSDAY => 4,
            self::FRIDAY => 5,
            self::SATURDAY => 6,
            self::SUNDAY => 7
        ];
        
        // Sort by day order and then by order_index
        return $results->sortBy(function ($item) use ($daysOrder) {
            return $daysOrder[$item->day_of_week] ?? 99;
        })->sortBy('order_index')->values();
    }

    public static function getTimetableForClass($classSection, $academicYear = null)
    {
        $query = self::where('class_section', $classSection)
                     ->where('is_active', true);
        
        if ($academicYear) {
            $query->where('academic_year', $academicYear);
        }
        
        $results = $query->get();
        
        // Define day order mapping
        $daysOrder = [
            self::MONDAY => 1,
            self::TUESDAY => 2,
            self::WEDNESDAY => 3,
            self::THURSDAY => 4,
            self::FRIDAY => 5,
            self::SATURDAY => 6,
            self::SUNDAY => 7
        ];
        
        // Sort by day order and then by order_index
        return $results->sortBy(function ($item) use ($daysOrder) {
            return $daysOrder[$item->day_of_week] ?? 99;
        })->sortBy('order_index')->values();
    }

    public function getFormattedTimeRange()
    {
        if ($this->start_time && $this->end_time) {
            return $this->start_time->format('h:i A') . ' - ' . $this->end_time->format('h:i A');
        }
        return 'Invalid Time';
    }

    /**
     * Only 'teaching' periods count toward capacity math and solver
     * placement (T2b); assembly/prayer/break/zero/dispersal are excluded
     * there but still print in the PDF grids, shaded.
     */
    public function scopeTeachingType($query)
    {
        return $query->where('period_type', self::PERIOD_TYPE_TEACHING);
    }

    
    /**
     * Get the user who created this bell timing.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}