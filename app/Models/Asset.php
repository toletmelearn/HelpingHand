<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class Asset extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'asset_code',
        'name',
        'category_id',
        'vendor',
        'cost',
        'purchase_date',
        'warranty_expiry_date',
        'warranty_details',
        'condition',
        'status',
        'description',
        'location',
        'serial_number',
        'quantity',
        'available_quantity',
        'is_active'
    ];
    
    protected $casts = [
        'purchase_date' => 'date',
        'warranty_expiry_date' => 'date',
        'cost' => 'decimal:2'
    ];
    
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }
    
    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }
}
