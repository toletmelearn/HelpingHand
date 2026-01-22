<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Exam extends Model
{
    protected $fillable = [
        'name', 'exam_type', 'class_name', 'subject', 'exam_date',
        'start_time', 'end_time', 'total_marks', 'passing_marks',
        'description', 'academic_year', 'term', 'status', 'created_by'
    ];
    
    protected $casts = [
        'exam_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'total_marks' => 'decimal:2',
        'passing_marks' => 'decimal:2',
    ];
    
    // Define relationship with results
    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }
    
    // Define relationship with creator
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
