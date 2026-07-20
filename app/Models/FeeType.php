<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\FeeStructureItem;
use App\Models\FeeCollectionItem;

class FeeType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_optional',
        'status',
        'category',
        'default_frequency',
        'default_charge_months',
        'default_late_fee_rule_id'
    ];

    protected $casts = [
        'is_optional' => 'boolean',
        'status' => 'string',
        'default_charge_months' => 'array'
    ];

    // Define relationship with late fee rule
    public function defaultLateFeeRule()
    {
        return $this->belongsTo(LateFeeRule::class, 'default_late_fee_rule_id');
    }

    // Define relationship with fee structure items
    public function feeStructureItems()
    {
        return $this->hasMany(FeeStructureItem::class);
    }

    // Define relationship with fee collection items
    public function feeCollectionItems()
    {
        return $this->hasMany(FeeCollectionItem::class);
    }

    // Scope for active fee types
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}