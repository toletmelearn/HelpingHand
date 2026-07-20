<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class FeeRefund extends Model
{
    use HasFactory, Auditable;

    protected static function booted()
    {
        static::saving(function ($model) {
            \App\Services\FinancialYearClosingService::validateSessionNotClosedForModel($model);
        });

        static::deleting(function ($model) {
            \App\Services\FinancialYearClosingService::validateSessionNotClosedForModel($model);
        });
    }

    protected $table = 'fee_refunds';

    protected $fillable = [
        'student_id',
        'fee_collection_id',
        'amount',
        'type', // refund, reversal
        'reason',
        'payment_mode',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'processed_by' => 'integer',
        'student_id' => 'integer',
        'fee_collection_id' => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id')->withTrashed();
    }

    public function feeCollection()
    {
        return $this->belongsTo(FeeCollection::class, 'fee_collection_id')->withTrashed();
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
