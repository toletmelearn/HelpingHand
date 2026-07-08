<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class ParentModel extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'parents';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'mobile',
        'admission_number',
        'password',
        'must_reset_password',
        'student_id',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'must_reset_password' => 'boolean',
    ];

    // Relationship with Student (legacy compatibility)
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'id')->withTrashed();
    }

    // Relationship with Students (1-to-many normalized structure)
    public function students()
    {
        return $this->hasMany(Student::class, 'parent_id');
    }

    // Dynamic resolve of student based on parent session context (supporting siblings switcher)
    public function getStudentAttribute()
    {
        if (request()->hasSession() && session()->has('active_student_id')) {
            $activeId = session('active_student_id');
            $activeStudent = $this->students()->where('id', $activeId)->first();
            if ($activeStudent) {
                return $activeStudent;
            }
        }

        return $this->belongsTo(Student::class, 'student_id', 'id')->withTrashed()->first();
    }

    // Dynamic resolve of student_id attribute based on parent session context
    public function getStudentIdAttribute($value)
    {
        if (request()->hasSession() && session()->has('active_student_id')) {
            $activeId = session('active_student_id');
            $hasChild = $this->students()->where('id', $activeId)->exists()
                || ($value == $activeId);
            if ($hasChild) {
                return (int) $activeId;
            }
        }

        return $value;
    }
}