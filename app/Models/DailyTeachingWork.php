<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DailyTeachingWork extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'date',
        'class_name',
        'section',
        'subject',
        'period_number',
        'teacher_id',
        'topic_covered',
        'teaching_summary',
        'attachments',
        'homework',
        'status',
        'academic_session',
        'syllabus_mapping',
        'created_by',
        'updated_by',
    ];
    
    protected $casts = [
        'date' => 'date',
        'attachments' => 'array',
        'homework' => 'array',
        'syllabus_mapping' => 'array',
        'period_number' => 'integer',
        'teacher_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];
    
    // Relationships
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }
    
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    
    // Scopes
    public function scopeForClass($query, $className)
    {
        return $query->where('class_name', $className);
    }
    
    public function scopeForSubject($query, $subject)
    {
        return $query->where('subject', $subject);
    }
    
    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }
    
    public function scopeForSection($query, $section)
    {
        return $query->where('section', $section);
    }
    
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }
    
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
    
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }
    
    // Helper methods
    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }
    
    public function hasHomework(): bool
    {
        return !empty($this->homework);
    }
    
    public function isForToday(): bool
    {
        return $this->date->format('Y-m-d') === today()->format('Y-m-d');
    }
    
    public function isPastDate(): bool
    {
        return $this->date->lt(today());
    }
    
    public function getAttachmentCount(): int
    {
        return $this->hasAttachments() ? count($this->attachments) : 0;
    }
}
