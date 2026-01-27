<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PerformanceScore extends Model
{
    protected $fillable = [
        'teacher_id',
        'period_start',
        'period_end',
        'period_type',
        'punctuality_score',
        'discipline_rating',
        'consistency_index',
        'performance_grade',
        'total_days',
        'present_days',
        'late_arrivals',
        'early_departures',
        'absent_days',
        'avg_working_hours',
        'detailed_metrics',
        'comments',
    ];
    
    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'punctuality_score' => 'decimal:2',
        'discipline_rating' => 'decimal:2',
        'consistency_index' => 'decimal:2',
        'avg_working_hours' => 'decimal:2',
        'detailed_metrics' => 'array',
        'total_days' => 'integer',
        'present_days' => 'integer',
        'late_arrivals' => 'integer',
        'early_departures' => 'integer',
        'absent_days' => 'integer',
    ];
    
    protected $appends = [
        'attendance_percentage',
        'punctuality_percentage',
        'overall_score',
    ];
    
    // Relationships
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
    
    // Accessors
    public function getAttendancePercentageAttribute()
    {
        if ($this->total_days > 0) {
            return round(($this->present_days / $this->total_days) * 100, 2);
        }
        return 0;
    }
    
    public function getPunctualityPercentageAttribute()
    {
        if ($this->present_days > 0) {
            $onTimeArrivals = $this->present_days - $this->late_arrivals;
            return round(($onTimeArrivals / $this->present_days) * 100, 2);
        }
        return 0;
    }
    
    public function getOverallScoreAttribute()
    {
        return round(($this->punctuality_score + $this->discipline_rating + $this->consistency_index) / 3, 2);
    }
    
    // Scopes
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }
    
    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->where('period_start', '>=', $startDate)
                    ->where('period_end', '<=', $endDate);
    }
    
    public function scopeMonthly($query)
    {
        return $query->where('period_type', 'monthly');
    }
    
    public function scopeWeekly($query)
    {
        return $query->where('period_type', 'weekly');
    }
    
    public function scopeDaily($query)
    {
        return $query->where('period_type', 'daily');
    }
    
    public function scopeForGrade($query, $grade)
    {
        return $query->where('performance_grade', $grade);
    }
    
    // Methods
    public static function calculateForTeacher($teacherId, $startDate, $endDate, $periodType = 'monthly')
    {
        $records = TeacherBiometricRecord::where('teacher_id', $teacherId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();
        
        if ($records->isEmpty()) {
            return null;
        }
        
        $totalDays = $records->count();
        $presentDays = $records->whereNotNull('first_in_time')->count();
        $lateArrivals = $records->where('arrival_status', 'late')->count();
        $earlyDepartures = $records->where('departure_status', 'early_exit')->count();
        $absentDays = $totalDays - $presentDays;
        
        $avgWorkingHours = $records->avg('calculated_duration') ?? 0;
        
        // Calculate punctuality score (0-100)
        $punctualityScore = $presentDays > 0 ? 
            round((($presentDays - $lateArrivals) / $presentDays) * 100, 2) : 0;
        
        // Calculate discipline rating (0-100)
        $disciplineRating = $totalDays > 0 ? 
            round((($totalDays - $absentDays) / $totalDays) * 100, 2) : 0;
        
        // Calculate consistency index (0-100)
        $workingHourDeviations = $records->pluck('calculated_duration')
            ->reject(fn($hours) => is_null($hours))
            ->map(fn($hours) => abs($hours - $avgWorkingHours))
            ->toArray();
        
        $consistencyIndex = !empty($workingHourDeviations) ? 
            round(100 - (array_sum($workingHourDeviations) / count($workingHourDeviations)), 2) : 100;
        
        // Determine performance grade
        $overallScore = ($punctualityScore + $disciplineRating + $consistencyIndex) / 3;
        $performanceGrade = self::getGradeFromScore($overallScore);
        
        $detailedMetrics = [
            'on_time_arrivals' => $presentDays - $lateArrivals,
            'early_departures' => $earlyDepartures,
            'half_days' => $records->where('departure_status', 'half_day')->count(),
            'avg_late_minutes' => $records->avg('late_minutes') ?? 0,
            'avg_early_departure_minutes' => $records->avg('early_departure_minutes') ?? 0,
        ];
        
        return self::updateOrCreate(
            [
                'teacher_id' => $teacherId,
                'period_start' => $startDate,
                'period_end' => $endDate,
                'period_type' => $periodType,
            ],
            [
                'punctuality_score' => $punctualityScore,
                'discipline_rating' => $disciplineRating,
                'consistency_index' => $consistencyIndex,
                'performance_grade' => $performanceGrade,
                'total_days' => $totalDays,
                'present_days' => $presentDays,
                'late_arrivals' => $lateArrivals,
                'early_departures' => $earlyDepartures,
                'absent_days' => $absentDays,
                'avg_working_hours' => $avgWorkingHours,
                'detailed_metrics' => $detailedMetrics,
            ]
        );
    }
    
    public static function getGradeFromScore($score)
    {
        if ($score >= 90) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B+';
        if ($score >= 60) return 'B';
        if ($score >= 50) return 'C';
        return 'D';
    }
    
    public function getComparisonData($compareWith = 'department')
    {
        // Implementation for comparative analytics
        return [];
    }
}
