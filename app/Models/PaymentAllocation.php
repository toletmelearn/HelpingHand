<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class PaymentAllocation extends Model
{
    use HasFactory, Auditable;

    protected $table = 'payment_allocations';

    protected $fillable = [
        'student_id',
        'credit_ledger_id',
        'debit_ledger_id',
        'amount',
        'allocation_type', // auto, manual
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'student_id' => 'integer',
        'credit_ledger_id' => 'integer',
        'debit_ledger_id' => 'integer',
    ];

    /**
     * Relationship with the student.
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * Relationship with the credit ledger entry (the payment).
     */
    public function creditLedger()
    {
        return $this->belongsTo(StudentFeeLedger::class, 'credit_ledger_id');
    }

    /**
     * Relationship with the debit ledger entry (the charge/due).
     */
    public function debitLedger()
    {
        return $this->belongsTo(StudentFeeLedger::class, 'debit_ledger_id');
    }
}
