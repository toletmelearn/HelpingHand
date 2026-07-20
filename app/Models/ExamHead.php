<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamHead extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'assigned_by',
        'assigned_at',
        'status',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    /**
     * Get the teacher who is the exam head.
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the user who assigned this exam head.
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Scope to get only active exam heads.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
