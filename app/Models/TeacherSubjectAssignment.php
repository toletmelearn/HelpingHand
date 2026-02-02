<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class TeacherSubjectAssignment extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'teacher_id',
        'subject_id',
        'class_id',
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

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    // Scopes
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeForSubject($query, $subjectId)
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeForClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    // Helper methods
    public function isPrimary(): bool
    {
        return $this->is_primary;
    }

    public function getClassName(): string
    {
        return $this->class ? $this->class->name : 'N/A';
    }

    public function getTeacherName(): string
    {
        return $this->teacher ? $this->teacher->name : 'N/A';
    }

    public function getSubjectName(): string
    {
        return $this->subject ? $this->subject->name : 'N/A';
    }
}