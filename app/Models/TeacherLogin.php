<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TeacherLogin extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'school_id',
        'username',
        'password',
        'status',
        'force_password_change',
        'last_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'force_password_change' => 'boolean',
        'last_login' => 'datetime',
    ];

    /**
     * Get the teacher that owns the login.
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
    
    /**
     * Get the teacher record directly (convenience method).
     */
    public function getTeacherRecord()
    {
        return $this->teacher;
    }

    /**
     * Get the school for multi-tenancy.
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Check if teacher is exam head.
     */
    public function isExamHead()
    {
        return ExamHead::where('teacher_id', $this->teacher_id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Check if teacher is exam cell member.
     */
    public function isExamCellMember()
    {
        return $this->teacher ? $this->teacher->isExamCellMember() : false;
    }

    /**
     * Get assigned classes and subjects.
     */
    public function assignments()
    {
        return TeacherClassSubjectAssignment::where('teacher_id', $this->teacher_id)
            ->with(['schoolClass', 'section', 'subject'])
            ->get();
    }

    /**
     * Scope to get only active logins.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Update last login timestamp.
     */
    public function updateLastLogin()
    {
        $this->update(['last_login' => now()]);
    }
}
