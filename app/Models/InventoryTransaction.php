<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class InventoryTransaction extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'asset_id',
        'user_id',
        'transaction_type',
        'quantity',
        'from_location',
        'to_location',
        'assigned_to',
        'reason',
        'notes'
    ];
    
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
