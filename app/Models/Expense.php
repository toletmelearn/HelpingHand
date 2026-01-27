<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'budget_id',
        'budget_category_id',
        'title',
        'description',
        'amount',
        'expense_date',
        'receipt_number',
        'vendor_name',
        'payment_method',
        'status',
        'approval_notes',
        'approved_by',
        'approved_at',
        'created_by'
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'approved_at' => 'datetime'
    ];
    
    // Relationships
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
    
    public function category(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class, 'budget_category_id');
    }
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    
    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
    
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
    
    public function scopeForBudget($query, $budgetId)
    {
        return $query->where('budget_id', $budgetId);
    }
    
    public function scopeForCategory($query, $categoryId)
    {
        return $query->where('budget_category_id', $categoryId);
    }
    
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }
    
    // Accessors
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => ucfirst($this->status)
        };
    }
    
    public function getPaymentMethodLabelAttribute()
    {
        return match($this->payment_method) {
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'cheque' => 'Cheque',
            'credit_card' => 'Credit Card',
            'online' => 'Online Payment',
            default => ucfirst(str_replace('_', ' ', $this->payment_method))
        };
    }
    
    // Methods
    public function approve($userId, $notes = null)
    {
        $this->status = 'approved';
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->approval_notes = $notes;
        $this->save();
        
        // Update budget spent amount
        $this->budget->spent_amount += $this->amount;
        $this->budget->save();
        
        return $this;
    }
    
    public function reject($userId, $notes = null)
    {
        $this->status = 'rejected';
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->approval_notes = $notes;
        $this->save();
        
        return $this;
    }
    
    public function isApproved()
    {
        return $this->status === 'approved';
    }
    
    public function canBeModified()
    {
        return $this->status === 'pending';
    }
}
