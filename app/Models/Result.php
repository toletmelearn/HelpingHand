<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    protected $fillable = [
        'student_id', 'exam_id', 'subject', 'marks_obtained', 'total_marks',
        'percentage', 'grade', 'academic_year', 'term', 'comments', 'result_status'
    ];
    
    protected $casts = [
        'marks_obtained' => 'decimal:2',
        'total_marks' => 'decimal:2',
        'percentage' => 'decimal:2',
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
    
    // Calculate percentage
    public function calculatePercentage(): float
    {
        if ($this->total_marks > 0) {
            return round(($this->marks_obtained / $this->total_marks) * 100, 2);
        }
        return 0;
    }
    
    // Determine grade based on percentage
    public function determineGrade(): string
    {
        $percentage = $this->calculatePercentage();
        
        if ($percentage >= 90) {
            return 'A+';
        } elseif ($percentage >= 80) {
            return 'A';
        } elseif ($percentage >= 70) {
            return 'B';
        } elseif ($percentage >= 60) {
            return 'C';
        } elseif ($percentage >= 50) {
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
}
