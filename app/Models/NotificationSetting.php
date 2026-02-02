<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type',
        'notification_type', // sms, email, both
        'is_enabled',
        'template_subject',
        'template_body',
        'recipients', // json array
        'conditions', // json array
        'schedule_type', // immediate, daily, weekly
        'created_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'recipients' => 'array',
        'conditions' => 'array',
    ];

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs()
    {
        return $this->hasMany(NotificationLog::class);
    }

    // Scopes
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeByEventType($query, $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeByNotificationType($query, $notificationType)
    {
        return $query->where('notification_type', $notificationType);
    }

    // Constants
    public const EVENT_TYPES = [
        'attendance_low' => 'Low Attendance Alert',
        'fee_due' => 'Fee Due Reminder',
        'exam_schedule' => 'Exam Schedule Notification',
        'result_published' => 'Result Published',
        'lesson_plan_updated' => 'Lesson Plan Updated',
        'birthday' => 'Birthday Wishes',
        'holiday' => 'Holiday Announcement',
        'event' => 'School Event',
    ];

    public const NOTIFICATION_TYPES = [
        'sms' => 'SMS Only',
        'email' => 'Email Only',
        'both' => 'SMS & Email',
    ];

    public const SCHEDULE_TYPES = [
        'immediate' => 'Immediate',
        'daily' => 'Daily Digest',
        'weekly' => 'Weekly Summary',
    ];
}
