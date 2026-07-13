<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdvanceRebateRule extends Model
{
    protected $fillable = [
        'name',
        'type', // percent, fixed
        'value',
        'applicable_fee_type_ids',
        'cutoff_month_day',
        'min_coverage',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'applicable_fee_type_ids' => 'array',
        'is_active' => 'boolean',
    ];

    public function studentAdvanceRebates(): HasMany
    {
        return $this->hasMany(StudentAdvanceRebate::class);
    }
}
