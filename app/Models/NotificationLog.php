<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_setting_id',
        'recipient_type', // student, parent, teacher, admin
        'recipient_id',
        'notification_type', // sms, email
        'subject',
        'message',
        'status', // pending, sent, failed
        'sent_at',
        'failed_reason',
        'retry_count',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    // Relationships
    public function notificationSetting()
    {
        return $this->belongsTo(NotificationSetting::class);
    }

    public function recipient()
    {
        // Polymorphic relationship - can be student, parent, teacher, etc.
        return $this->morphTo();
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByNotificationType($query, $type)
    {
        return $query->where('notification_type', $type);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // Constants
    public const STATUSES = [
        'pending' => 'Pending',
        'sent' => 'Sent',
        'failed' => 'Failed',
    ];

    public const RECIPIENT_TYPES = [
        'student' => 'Student',
        'parent' => 'Parent',
        'teacher' => 'Teacher',
        'admin' => 'Administrator',
        'class' => 'Entire Class',
        'section' => 'Entire Section',
    ];
}
