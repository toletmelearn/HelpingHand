<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherSubstitution extends Model
{
    use HasFactory;

    protected $fillable = [
        'substitution_date',
        'absent_teacher_id',
        'substitute_teacher_id',
        'class_id',
        'section_id',
        'subject_id',
        'period_number',
        'period_name',
        'status',
        'reason',
        'created_by',
        'updated_by',
        'assigned_at',
    ];

    protected $casts = [
        'substitution_date' => 'date',
        'assigned_at' => 'datetime',
    ];

    // Relationships
    public function absentTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'absent_teacher_id');
    }

    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'substitute_teacher_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('substitution_date', $date);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAssigned($query)
    {
        return $query->where('status', 'assigned');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where(function($q) use ($teacherId) {
            $q->where('absent_teacher_id', $teacherId)
              ->orWhere('substitute_teacher_id', $teacherId);
        });
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAssigned(): bool
    {
        return $this->status === 'assigned';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getReadableStatus(): string
    {
        $statuses = [
            'pending' => 'Pending',
            'assigned' => 'Assigned',
            'approved' => 'Approved',
            'cancelled' => 'Cancelled',
        ];

        return $statuses[$this->status] ?? ucfirst($this->status);
    }

    public function isToday(): bool
    {
        return $this->substitution_date->format('Y-m-d') === now()->format('Y-m-d');
    }
}