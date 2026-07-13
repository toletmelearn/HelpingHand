<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class PaymentClaim extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'student_id',
        'claim_type', // upi, bank_cash_deposit
        'reference_token',
        'utr',
        'deposit_date',
        'branch',
        'amount',
        'screenshot_path',
        'status', // claimed, matched, rejected, cancelled
        'bank_statement_row_id',
        'fee_collection_id',
        'match_confidence', // exact, narration, fuzzy, cash_deposit
        'submitted_at',
        'resolved_by',
        'resolved_at',
        'cancellation_reason',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'deposit_date' => 'date',
        'submitted_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function bankStatementRow(): BelongsTo
    {
        return $this->belongsTo(BankStatementRow::class);
    }

    public function feeCollection(): BelongsTo
    {
        return $this->belongsTo(FeeCollection::class)->withTrashed();
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
