<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LateFeeRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type', // flat, daily_incremental, slab
        'amount',
        'grace_days',
        'slab_config',
        'max_limit'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'grace_days' => 'integer',
        'slab_config' => 'array',
        'max_limit' => 'decimal:2'
    ];

    /**
     * Get the fee structure items that use this late fee rule.
     */
    public function feeStructureItems(): HasMany
    {
        return $this->hasMany(FeeStructureItem::class);
    }
}
