<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'date',
        'status',
        'remarks',
        'marked_by',
        'updated_by'
    ];

    protected $casts = [
        'date' => 'date'
    ];

    // Relationships
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', 'absent');
    }

    public function scopeLate($query)
    {
        return $query->where('status', 'late');
    }

    public function scopeHalfDay($query)
    {
        return $query->where('status', 'half_day');
    }

    // Helper methods
    public function isPresent(): bool
    {
        return $this->status === 'present';
    }

    public function isAbsent(): bool
    {
        return $this->status === 'absent';
    }

    public function isLate(): bool
    {
        return $this->status === 'late';
    }

    public function isHalfDay(): bool
    {
        return $this->status === 'half_day';
    }

    public function getStatusBadge(): string
    {
        $badges = [
            'present' => 'success',
            'absent' => 'danger',
            'late' => 'warning',
            'half_day' => 'info'
        ];

        return $badges[$this->status] ?? 'secondary';
    }

    public function getStatusText(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    public function getStatusIcon(): string
    {
        $icons = [
            'present' => 'bi-check-circle',
            'absent' => 'bi-x-circle',
            'late' => 'bi-exclamation-triangle',
            'half_day' => 'bi-dash-circle'
        ];

        return $icons[$this->status] ?? 'bi-clock';
    }
}