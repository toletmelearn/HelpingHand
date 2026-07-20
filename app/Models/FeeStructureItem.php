<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\FeeStructure;
use App\Models\FeeType;

class FeeStructureItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'fee_structure_id',
        'fee_type_id',
        'amount',
        'due_day',
        'billing_frequency',
        'charge_months',
        'late_fee_rule_id',
        'exam_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_day' => 'integer',
        'billing_frequency' => 'string',
        'charge_months' => 'array',
        'late_fee_rule_id' => 'integer',
        'exam_id' => 'integer'
    ];

    // Define relationship with fee structure
    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    // Define relationship with fee type
    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    // Define relationship with late fee rule
    public function lateFeeRule(): BelongsTo
    {
        return $this->belongsTo(LateFeeRule::class);
    }

    // Define relationship with custom installments
    public function installments()
    {
        return $this->hasMany(FeeStructureItemInstallment::class);
    }
}