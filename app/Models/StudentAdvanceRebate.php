<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class StudentAdvanceRebate extends Model
{
    use Auditable;

    protected $fillable = [
        'student_id',
        'advance_rebate_rule_id',
        'academic_year',
        'fee_collection_id',
        'fee_type_id',
        'rebate_amount',
        'status', // applied, clawed_back
        'clawback_amount',
        'clawback_shortfall_amount',
        'applied_at',
        'clawed_back_at',
        'approved_by',
        'remarks',
    ];

    protected $casts = [
        'rebate_amount' => 'decimal:2',
        'clawback_amount' => 'decimal:2',
        'clawback_shortfall_amount' => 'decimal:2',
        'applied_at' => 'datetime',
        'clawed_back_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AdvanceRebateRule::class, 'advance_rebate_rule_id');
    }

    public function feeCollection(): BelongsTo
    {
        return $this->belongsTo(FeeCollection::class)->withTrashed();
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
