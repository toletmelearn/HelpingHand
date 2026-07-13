<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class BankStatementRow extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'import_session_id',
        'row_number',
        'transaction_date',
        'amount',
        'utr',
        'narration',
        'status', // unmatched, matched, ignored
        'payment_claim_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function importSession(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class);
    }

    public function paymentClaim(): BelongsTo
    {
        return $this->belongsTo(PaymentClaim::class);
    }
}
