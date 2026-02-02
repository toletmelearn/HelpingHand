<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PerformanceAnalytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'metric_type',
        'metric_value',
        'date_recorded',
        'user_id',
        'module_accessed',
        'duration', // in seconds
        'ip_address',
        'user_agent',
        'additional_data',
    ];

    protected $casts = [
        'date_recorded' => 'date',
        'duration' => 'integer',
        'additional_data' => 'array',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByMetricType($query, $metricType)
    {
        return $query->where('metric_type', $metricType);
    }

    public function scopeByModule($query, $module)
    {
        return $query->where('module_accessed', $module);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date_recorded', [$startDate, $endDate]);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Constants
    public const METRIC_TYPES = [
        'login_frequency' => 'Login Frequency',
        'module_usage' => 'Module Usage',
        'teacher_compliance' => 'Teacher Compliance',
        'student_academic_trends' => 'Student Academic Trends',
        'attendance_patterns' => 'Attendance Patterns',
        'page_load_time' => 'Page Load Time',
        'session_duration' => 'Session Duration',
        'error_rate' => 'Error Rate',
    ];

    public const MODULES = [
        'dashboard',
        'students',
        'teachers',
        'attendance',
        'fees',
        'exams',
        'reports',
        'settings',
        'biometric',
        'library',
        'inventory',
        'notifications',
        'analytics',
    ];
}
