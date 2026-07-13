<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class StudentDiscountApplied extends Model
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

    protected $table = 'student_discounts_applied';

    protected $fillable = [
        'student_id',
        'discount_rule_id',
        'fee_type_id',
        'amount',
        'month',
        'academic_year',
        'applied_at',
        'approved_by',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function discountRule()
    {
        return $this->belongsTo(DiscountRule::class, 'discount_rule_id');
    }

    public function feeType()
    {
        return $this->belongsTo(FeeType::class, 'fee_type_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
