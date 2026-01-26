<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BellSchedule extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'season_type',
        'start_date',
        'end_date',
        'status',
        'periods',
        'target_group',
        'created_by',
        'updated_by',
    ];
    
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'periods' => 'array',
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
    
    public function specialDayOverrides(): HasMany
    {
        return $this->hasMany(SpecialDayOverride::class, 'bell_schedule_id');
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    
    public function scopeForSeason($query, $seasonType)
    {
        return $query->where('season_type', $seasonType);
    }
    
    public function scopeForTargetGroup($query, $targetGroup)
    {
        return $query->where('target_group', $targetGroup);
    }
    
    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
    
    public function isCurrentSeason(): bool
    {
        $today = now()->toDateString();
        return $today >= $this->start_date->format('Y-m-d') && $today <= $this->end_date->format('Y-m-d');
    }
    
    public function getPeriodCount(): int
    {
        return $this->periods ? count($this->periods) : 0;
    }
    
    public function getTeachingPeriods()
    {
        if (!$this->periods) return [];
        
        return array_filter($this->periods, function($period) {
            return isset($period['type']) && $period['type'] === 'teaching_period';
        });
    }
    
    public function getBreakPeriods()
    {
        if (!$this->periods) return [];
        
        return array_filter($this->periods, function($period) {
            return isset($period['type']) && in_array($period['type'], ['short_break', 'lunch_break']);
        });
    }
}
