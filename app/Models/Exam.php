<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Exam extends Model
{
    protected $fillable = [
        'name', 'exam_type', 'class_id', 'subject_id', 'class_name', 'subject', 'exam_date',
        'start_time', 'end_time', 'total_marks', 'passing_marks',
        'description', 'academic_year', 'term', 'status', 'created_by'
    ];
    
    protected $casts = [
        'exam_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'total_marks' => 'float',
        'passing_marks' => 'float',
    ];
    
    // Accessor for max_marks (alias for total_marks)
    public function getMaxMarksAttribute()
    {
        return $this->total_marks;
    }
    
    // Define relationship with results
    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }

    public function blueprints(): HasMany
    {
        return $this->hasMany(ExamBlueprint::class);
    }
    
    // Define relationship with creator
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'created_by');
    }
    
    // Define relationship with school class using class_name
    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_name', 'name');
    }
    
    // Define relationship with subject using subject name
    public function subjectInfo()
    {
        return $this->belongsTo(Subject::class, 'subject', 'name');
    }

    // FK-based subject relation (subject_id is now populated + enforced
    // NOT NULL -- see 2026_08_29 migrations). subjectInfo() above still
    // reads the free-text `subject` column and is left untouched since
    // existing callers depend on it; this is the new, correct relation for
    // anything written going forward.
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    // Invigilators assigned to this exam (exam_invigilator_duties pivot).
    public function invigilators()
    {
        return $this->belongsToMany(Teacher::class, 'exam_invigilator_duties', 'exam_id', 'teacher_id')
            ->withPivot('room_number', 'role', 'assigned_by', 'assigned_at', 'notes')
            ->withTimestamps();
    }

    public function invigilatorDuties(): HasMany
    {
        return $this->hasMany(ExamInvigilatorDuty::class);
    }

    public function relievingDuties(): HasMany
    {
        return $this->hasMany(ExamRelievingDuty::class);
    }
    
    // Define relationship with teacher who created the exam
    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'created_by');
    }
    
    // Check if exam is ongoing
    public function isOngoing(): bool
    {
        $now = now();
        $examDate = $this->exam_date;
        $startTime = $this->start_time;
        $endTime = $this->end_time;
        
        // Combine date and time
        $examStartDateTime = $examDate->setTime($startTime->hour, $startTime->minute);
        $examEndDateTime = $examDate->setTime($endTime->hour, $endTime->minute);
        
        return $now >= $examStartDateTime && $now <= $examEndDateTime;
    }
    
    // Define relationship with exam papers
    public function examPapers()
    {
        return $this->hasMany(ExamPaper::class, 'exam_id');
    }
}