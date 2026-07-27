<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeacherClassSubjectAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'class_id',
        'section_id',
        'subject_id',
        'is_class_teacher',
        'is_primary_subject_teacher',
        'academic_year',
        'periods_per_week',
        'require_consecutive',
    ];

    protected $casts = [
        'is_class_teacher' => 'boolean',
        'is_primary_subject_teacher' => 'boolean',
        'require_consecutive' => 'boolean',
    ];

    /**
     * Get the teacher that owns the assignment.
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the class for the assignment.
     */
    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Get the section for the assignment.
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Get the subject for the assignment.
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
