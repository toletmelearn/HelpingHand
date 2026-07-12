<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\FeeCollection;
use App\Models\FeeType;

class FeeCollectionItem extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::created(function ($item) {
            // Fires no matter which of the several collection paths created
            // this item (counter collection, quick-collect, online payment
            // allocation) -- same reasoning as FeeCollection's own booted()
            // hook centralizing ledger-posting instead of duplicating it
            // per controller.
            if (
                $item->feeType
                && $item->feeType->category === 'deposit'
                && \Illuminate\Support\Facades\Schema::hasTable('security_deposits')
            ) {
                \App\Models\SecurityDeposit::create([
                    'student_id' => $item->feeCollection->student_id,
                    'fee_type_id' => $item->fee_type_id,
                    'fee_collection_id' => $item->fee_collection_id,
                    'amount' => $item->amount,
                    'status' => 'held',
                ]);
            }
        });
    }

    protected $fillable = [
        'fee_collection_id',
        'fee_type_id',
        'amount'
    ];

    protected $casts = [
        'amount' => 'decimal:2'
    ];

    // Define relationship with fee collection
    public function feeCollection(): BelongsTo
    {
        return $this->belongsTo(FeeCollection::class);
    }

    // Define relationship with fee type
    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }
}