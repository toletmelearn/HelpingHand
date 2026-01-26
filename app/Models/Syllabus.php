<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Syllabus extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'subject',
        'class_name',
        'section',
        'title',
        'description',
        'chapters',
        'start_date',
        'end_date',
        'academic_session',
        'total_duration_hours',
        'learning_objectives',
        'assessment_criteria',
        'status',
        'created_by',
        'updated_by',
    ];
    
    protected $casts = [
        'chapters' => 'array',
        'learning_objectives' => 'array',
        'assessment_criteria' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'total_duration_hours' => 'decimal:2',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];
    
    // Relationships
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    
    public function dailyTeachingWorks(): HasMany
    {
        return $this->hasMany(DailyTeachingWork::class, 'syllabus_mapping->syllabus_id'); // This will be handled differently in practice
    }
    
    // Scopes
    public function scopeForSubject($query, $subject)
    {
        return $query->where('subject', $subject);
    }
    
    public function scopeForClass($query, $className)
    {
        return $query->where('class_name', $className);
    }
    
    public function scopeForSection($query, $section)
    {
        return $query->where('section', $section);
    }
    
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    
    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }
    
    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
    
    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }
    
    public function hasChapters(): bool
    {
        return !empty($this->chapters);
    }
    
    public function getChapterCount(): int
    {
        return $this->hasChapters() ? count($this->chapters) : 0;
    }
    
    public function isWithinDates($date = null): bool
    {
        $checkDate = $date ?: today();
        
        if (!$this->start_date || !$this->end_date) {
            return true; // If no dates set, consider it valid
        }
        
        return $checkDate->between($this->start_date, $this->end_date);
    }
    
    public function getDurationPercentage(): float
    {
        if (!$this->start_date || !$this->end_date || !$this->total_duration_hours) {
            return 0;
        }
        
        $totalDays = $this->start_date->diffInDays($this->end_date);
        $elapsedDays = $this->start_date->diffInDays(today());
        
        if ($totalDays <= 0) return 0;
        
        return min(100, ($elapsedDays / $totalDays) * 100);
    }
}
