<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SpecialDayOverride extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'override_date',
        'type',
        'bell_schedule_id',
        'custom_periods',
        'remarks',
        'created_by',
        'updated_by',
    ];
    
    protected $casts = [
        'override_date' => 'date',
        'custom_periods' => 'array',
        'bell_schedule_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];
    
    // Relationships
    public function bellSchedule(): BelongsTo
    {
        return $this->belongsTo(BellSchedule::class, 'bell_schedule_id');
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
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('override_date', $date);
    }
    
    public function scopeForType($query, $type)
    {
        return $query->where('type', $type);
    }
    
    // Helper methods
    public function isToday(): bool
    {
        return $this->override_date->format('Y-m-d') === now()->format('Y-m-d');
    }
    
    public function getReadableType(): string
    {
        $types = [
            'exam_day' => 'Exam Day',
            'half_day' => 'Half Day',
            'event_day' => 'Event Day',
            'emergency_closure' => 'Emergency Closure',
        ];
        
        return $types[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }
}
