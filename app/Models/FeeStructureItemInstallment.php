<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FeeStructureItemInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'fee_structure_item_id',
        'month',
        'amount'
    ];

    protected $casts = [
        'amount' => 'decimal:2'
    ];

    /**
     * Get the fee structure item that owns the installment.
     */
    public function feeStructureItem(): BelongsTo
    {
        return $this->belongsTo(FeeStructureItem::class);
    }
}
