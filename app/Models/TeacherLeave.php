<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Teacher;
use App\Models\User;

class TeacherLeave extends Model
{
    protected $fillable = [
        'teacher_id',
        'leave_type',
        'start_date',
        'end_date',
        'days',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'approval_notes'
    ];
    
    protected $dates = ['start_date', 'end_date', 'approved_at'];
    
    protected $casts = [
        'days' => 'integer'
    ];
    
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
    
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    
    public function getDurationAttribute()
    {
        $startDate = $this->start_date->format('d/m/Y');
        $endDate = $this->end_date->format('d/m/Y');
        return "{$startDate} - {$endDate}";
    }
}
