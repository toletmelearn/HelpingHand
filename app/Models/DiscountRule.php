<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class DiscountRule extends Model
{
    use HasFactory, Auditable;

    protected $table = 'discount_rules';

    protected $fillable = [
        'name',
        'type', // sibling, staff_child, merit, category, rte_quota, early_payment, gender_based
        'config',
        'priority',
        'is_active',
        'discount_mode', // percentage|flat_amount
        'flat_amount',
        'valid_from',
        'valid_until',
        'max_cap_amount',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'flat_amount' => 'float',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'max_cap_amount' => 'float',
    ];

    public function appliedDiscounts()
    {
        return $this->hasMany(StudentDiscountApplied::class, 'discount_rule_id');
    }
}
