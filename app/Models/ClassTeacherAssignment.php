<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassTeacherAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'assigned_class',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'teacher_id');
    }

    // Scopes
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeForClass($query, $className)
    {
        return $query->where('assigned_class', $className);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isCurrentlyAssigned(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $today = now()->toDateString();
        if ($this->start_date && $this->end_date) {
            return $today >= $this->start_date->format('Y-m-d') && $today <= $this->end_date->format('Y-m-d');
        }

        // If no date range specified, assume always active if is_active is true
        return true;
    }

    public function getClassTeacherName(): string
    {
        return $this->teacher ? $this->teacher->name : 'Unknown Teacher';
    }
}