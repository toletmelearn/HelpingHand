<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HomeworkNotice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'type', // 'homework', 'notice', 'announcement'
        'class_id',
        'section_id',
        'subject_id',
        'assigned_by',
        'due_date',
        'publish_date',
        'status',
        'priority',
        'visible_to_parent',
        'attachment_path',
        'parent_notes'
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'publish_date' => 'datetime',
        'status' => 'string',
        'priority' => 'string',
        'visible_to_parent' => 'boolean'
    ];

    // Define relationship with class
    public function schoolClass()
    {
        return $this->belongsTo(\App\Models\SchoolClass::class, 'class_id');
    }

    // Define relationship with subject
    public function subject()
    {
        return $this->belongsTo(\App\Models\Subject::class, 'subject_id');
    }

    // Define relationship with assigned teacher
    public function teacherLogin()
    {
        return $this->belongsTo(\App\Models\TeacherLogin::class, 'assigned_by');
    }

    // Define relationship with section
    public function section()
    {
        return $this->belongsTo(\App\Models\Section::class);
    }

    // Scope for active notices
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Scope for published notices
    public function scopePublished($query)
    {
        return $query->where('publish_date', '<=', now());
    }

    // Scope for homework type
    public function scopeHomework($query)
    {
        return $query->where('type', 'homework');
    }

    // Scope for notices type
    public function scopeNotices($query)
    {
        return $query->where('type', 'notice');
    }

    // Scope for upcoming due homework
    public function scopeUpcomingDue($query)
    {
        return $query->where('due_date', '>=', today())
                    ->where('due_date', '<=', today()->addDays(7));
    }
}