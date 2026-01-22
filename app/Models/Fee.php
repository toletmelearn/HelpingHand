<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fee extends Model
{
    protected $fillable = [
        'student_id', 'fee_structure_id', 'academic_year', 'term', 
        'amount', 'paid_amount', 'due_amount', 'status', 'due_date', 
        'payment_date', 'notes'
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'due_date' => 'date',
        'payment_date' => 'date',
    ];
    
    // Define relationship with student
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
    
    // Define relationship with fee structure
    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }
    
    // Calculate due amount
    public function calculateDueAmount(): float
    {
        return $this->amount - $this->paid_amount;
    }
    
    // Update status based on payment
    public function updateStatus(): void
    {
        $due = $this->calculateDueAmount();
        
        if ($due <= 0) {
            $this->status = 'paid';
        } elseif ($due < $this->amount) {
            $this->status = 'partial';
        } else {
            $this->status = 'pending';
        }
        
        $this->due_amount = $due;
        $this->save();
    }
}
