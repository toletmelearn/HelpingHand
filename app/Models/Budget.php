<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Budget extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'description',
        'fiscal_year',
        'total_amount',
        'allocated_amount',
        'spent_amount',
        'status',
        'approval_date',
        'lock_date',
        'close_date',
        'approved_by',
        'created_by'
    ];
    
    protected $casts = [
        'total_amount' => 'decimal:2',
        'allocated_amount' => 'decimal:2',
        'spent_amount' => 'decimal:2',
        'approval_date' => 'date',
        'lock_date' => 'date',
        'close_date' => 'date'
    ];
    
    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(BudgetCategory::class, 'budget_allocations')
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
        return $query->whereIn('status', ['draft', 'approved']);
    }
    
    public function scopeForYear($query, $year)
    {
        return $query->where('fiscal_year', $year);
    }
    
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
    
    // Accessors
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'draft' => 'Draft',
            'approved' => 'Approved',
            'locked' => 'Locked',
            'closed' => 'Closed',
            default => ucfirst($this->status)
        };
    }
    
    public function getUtilizationPercentageAttribute()
    {
        if ($this->allocated_amount == 0) return 0;
        return round(($this->spent_amount / $this->allocated_amount) * 100, 2);
    }
    
    public function getRemainingPercentageAttribute()
    {
        return 100 - $this->utilization_percentage;
    }
    
    // Methods
    public function isOverBudget()
    {
        return $this->spent_amount > $this->allocated_amount;
    }
    
    public function getOverBudgetAmount()
    {
        if (!$this->isOverBudget()) return 0;
        return $this->spent_amount - $this->allocated_amount;
    }
    
    public function canBeModified()
    {
        return in_array($this->status, ['draft', 'approved']);
    }
    
    public function getTotalExpensesByCategory($categoryId)
    {
        return $this->expenses()
                   ->where('budget_category_id', $categoryId)
                   ->sum('amount');
    }
    
    public function allocateToCategory($categoryId, $amount)
    {
        $this->categories()->attach($categoryId, [
            'allocated_amount' => $amount,
            'spent_amount' => 0
        ]);
        
        $this->allocated_amount += $amount;
        $this->save();
    }
}
