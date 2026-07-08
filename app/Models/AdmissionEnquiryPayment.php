<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionEnquiryPayment extends Model
{
    protected $fillable = [
        'admission_enquiry_id',
        'fee_type_id',
        'amount',
        'payment_mode',
        'receipt_no',
        'paid_at',
        'collected_by',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public const PAYMENT_MODES = [
        'cash' => 'Cash',
        'cheque' => 'Cheque',
        'upi' => 'UPI',
        'bank_transfer' => 'Bank Transfer',
    ];

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(AdmissionEnquiry::class, 'admission_enquiry_id');
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
