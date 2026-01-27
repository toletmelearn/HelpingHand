<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Teacher;
use App\Models\AuditLog;

class TeacherBiometricRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'teacher_id',
        'date',
        'first_in_time',
        'last_out_time',
        'multiple_punches',
        'calculated_duration',
        'arrival_status',
        'departure_status',
        'grace_minutes_used',
        'late_minutes',
        'early_departure_minutes',
        'remarks',
        'import_source',
        'biometric_device_id',
        'device_punch_id',
        'sync_timestamp',
        'is_synced',
        'sync_log_id',
    ];

    protected $casts = [
        'date' => 'date',
        'first_in_time' => 'datetime:H:i:s',
        'last_out_time' => 'datetime:H:i:s',
        'multiple_punches' => 'array',
        'calculated_duration' => 'decimal:2',
        'grace_minutes_used' => 'integer',
        'late_minutes' => 'integer',
        'early_departure_minutes' => 'integer',
        'sync_timestamp' => 'datetime',
        'is_synced' => 'boolean',
    ];

    protected $appends = [
        'working_duration_formatted',
        'status_badge',
    ];

    // Relationships
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
    
    public function biometricDevice()
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }
    
    public function syncLog()
    {
        return $this->belongsTo(BiometricSyncLog::class, 'sync_log_id');
    }

    // Scopes
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByArrivalStatus($query, $status)
    {
        return $query->where('arrival_status', $status);
    }

    public function scopeByDepartureStatus($query, $status)
    {
        return $query->where('departure_status', $status);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('date', '>=', now()->subDays($days));
    }

    // Accessors
    public function getWorkingDurationFormattedAttribute()
    {
        if (!$this->calculated_duration) {
            return 'N/A';
        }
        
        $hours = floor($this->calculated_duration);
        $minutes = round(($this->calculated_duration - $hours) * 60);
        
        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }
        
        return "{$minutes}m";
    }

    public function getStatusBadgeAttribute()
    {
        $badgeClass = '';
        $text = '';
        
        if ($this->arrival_status === 'late') {
            $badgeClass = 'bg-warning';
            $text = 'Late Arrival';
        } elseif ($this->departure_status === 'early_exit') {
            $badgeClass = 'bg-info';
            $text = 'Early Exit';
        } elseif ($this->departure_status === 'half_day') {
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

    // Helper Methods
    public function isLateArrival()
    {
        return $this->arrival_status === 'late';
    }

    public function isEarlyDeparture()
    {
        return $this->departure_status === 'early_exit';
    }

    public function isHalfDay()
    {
        return $this->departure_status === 'half_day';
    }

    public function isOnTime()
    {
        return $this->arrival_status === 'on_time' && $this->departure_status === 'on_time';
    }

    // Audit Logging
    public static function boot()
    {
        parent::boot();
        
        static::created(function ($model) {
            self::logActivity('create', $model);
        });
        
        static::updated(function ($model) {
            self::logActivity('update', $model);
        });
        
        static::deleted(function ($model) {
            self::logActivity('delete', $model);
        });
    }
    
    protected static function logActivity($action, $model)
    {
        // Only log if we have an authenticated user
        if (auth()->guard()->check()) {
            AuditLog::create([
                'user_type' => get_class(auth()->guard()->user()),
                'user_id' => auth()->guard()->id(),
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'action' => $action,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'performed_at' => now(),
            ]);
        }
    }

    // Static Methods for Reports
    public static function getDailySummary($date)
    {
        return self::where('date', $date)
            ->selectRaw('COUNT(*) as total, 
                         SUM(CASE WHEN arrival_status = "late" THEN 1 ELSE 0 END) as late_arrivals,
                         SUM(CASE WHEN departure_status = "early_exit" THEN 1 ELSE 0 END) as early_departures,
                         SUM(CASE WHEN departure_status = "half_day" THEN 1 ELSE 0 END) as half_days,
                         AVG(calculated_duration) as avg_working_hours')
            ->first();
    }

    public static function getMonthlyReport($teacherId, $year, $month)
    {
        return self::where('teacher_id', $teacherId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->selectRaw('COUNT(*) as total_days,
                         SUM(CASE WHEN arrival_status = "late" THEN 1 ELSE 0 END) as late_days,
                         SUM(CASE WHEN departure_status = "early_exit" THEN 1 ELSE 0 END) as early_exit_days,
                         SUM(CASE WHEN departure_status = "half_day" THEN 1 ELSE 0 END) as half_days,
                         AVG(calculated_duration) as avg_working_hours,
                         MIN(date) as from_date,
                         MAX(date) as to_date')
            ->first();
    }
}
