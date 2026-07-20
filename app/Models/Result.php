<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    protected $fillable = [
        'student_id', 'exam_id', 'subject', 'marks_obtained', 'total_marks',
        'percentage', 'grade', 'academic_year', 'term', 'comments', 'remarks', 
        'result_status', 'class_rank', 'section_rank', 'is_locked', 'generated_by', 'generated_at',
        'uploaded_by_teacher_id', 'uploaded_at', 'status',
        'original_marks_obtained', 'grace_marks_applied', 'moderated_by', 'moderation_reason'
    ];
    
    protected $casts = [
        'marks_obtained' => 'float',
        'total_marks' => 'float',
        'percentage' => 'float',
        'class_rank' => 'integer',
        'section_rank' => 'integer',
        'is_locked' => 'boolean',
        'generated_at' => 'datetime',
        'uploaded_at' => 'datetime',
        'additional_data' => 'array',
    ];
    
    // Define relationship with student
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
    
    // Define relationship with exam
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
    
    // Define relationship with user who generated the result
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
    
    // Define relationship with teacher who uploaded the result
    public function uploadedByTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'uploaded_by_teacher_id');
    }
    
    // Define relationship with school class
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
    
    // Calculate percentage
    public function calculatePercentage(): float
    {
        if ($this->total_marks > 0) {
            return round(($this->marks_obtained / $this->total_marks) * 100, 2);
        }
        return 0;
    }
    
    // Determine grade based on percentage (CBSE style)
    public function determineGrade(): string
    {
        $percentage = $this->calculatePercentage();
        
        if ($percentage >= 90) {
            return 'A1';
        } elseif ($percentage >= 80) {
            return 'A2';
        } elseif ($percentage >= 70) {
            return 'B1';
        } elseif ($percentage >= 60) {
            return 'B2';
        } elseif ($percentage >= 50) {
            return 'C';
        } elseif ($percentage >= 40) {
            return 'D';
        } else {
            return 'F';
        }
    }
    
    // Update result status based on marks
    public function updateResultStatus(): void
    {
        $percentage = $this->calculatePercentage();
        
        if ($percentage >= 33) { // Assuming 33% is passing threshold
            $this->result_status = 'pass';
        } else {
            $this->result_status = 'fail';
        }
        
        $this->percentage = $percentage;
        $this->grade = $this->determineGrade();
        $this->save();
    }
    
    // Check if result is locked
    public function isLocked(): bool
    {
        return $this->is_locked;
    }
    
    // Lock the result
    public function lock(): void
    {
        $this->is_locked = true;
        $this->save();
    }
    
    // Unlock the result
    public function unlock(): void
    {
        $this->is_locked = false;
        $this->save();
    }
    
    // Get formatted result data for report card
    public function getReportCardData(): array
    {
        return [
            'student_id' => $this->student_id,
            'exam_id' => $this->exam_id,
            'subject' => $this->subject,
            'marks_obtained' => $this->marks_obtained,
            'total_marks' => $this->total_marks,
            'percentage' => $this->percentage,
            'grade' => $this->grade,
            'result_status' => $this->result_status,
            'remarks' => $this->remarks ?? $this->comments,
            'class_rank' => $this->class_rank,
            'section_rank' => $this->section_rank,
        ];
    }
}
