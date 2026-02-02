<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class TeacherClassAssignment extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'teacher_id',
        'class_id',
        'subject_assigned',
        'role',
        'is_primary',
        'assigned_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'assigned_at' => 'datetime',
    ];

    // Relationships
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function class()
    {
        return $this->belongsTo(ClassManagement::class, 'class_id');
    }

    // Scopes
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeForClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeRole($query, $role)
    {
        return $query->where('role', $role);
    }

    // Helper methods
    public function isPrimary(): bool
    {
        return $this->is_primary;
    }

    public function getRoleLabel(): string
    {
        $labels = [
            'class_teacher' => 'Class Teacher',
            'subject_teacher' => 'Subject Teacher',
            'assistant_teacher' => 'Assistant Teacher',
        ];
        
        return $labels[$this->role] ?? ucfirst(str_replace('_', ' ', $this->role));
    }

    public function getClassName(): string
    {
        return $this->class ? $this->class->name : 'N/A';
    }

    public function getTeacherName(): string
    {
        return $this->teacher ? $this->teacher->name : 'N/A';
    }
}