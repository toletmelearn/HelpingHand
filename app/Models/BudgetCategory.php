<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BudgetCategory extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'description',
        'type',
        'default_allocation_percentage',
        'is_active',
        'created_by'
    ];
    
    protected $casts = [
        'default_allocation_percentage' => 'decimal:2',
        'is_active' => 'boolean'
    ];
    
    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function budgets(): BelongsToMany
    {
        return $this->belongsToMany(Budget::class, 'budget_allocations')
                   ->withPivot('allocated_amount', 'spent_amount')
                   ->withTimestamps();
    }
    
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }
    
    // Accessors
    public function getTypeLabelAttribute()
    {
        return match($this->type) {
            'operational' => 'Operational',
            'capital' => 'Capital',
            'maintenance' => 'Maintenance',
            default => ucfirst($this->type)
        };
    }
    
    // Methods
    public function getTotalExpensesAttribute()
    {
        return $this->expenses()->sum('amount');
    }
    
    public function getFormattedPercentageAttribute()
    {
        return $this->default_allocation_percentage . '%';
    }
}
