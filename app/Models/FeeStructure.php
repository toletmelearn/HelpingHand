<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeStructure extends Model
{
    protected $fillable = [
        'name', 'class_name', 'term', 'amount', 'description', 
        'is_active', 'frequency', 'valid_from', 'valid_until'
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];
    
    // Define relationship with fees
    public function fees(): HasMany
    {
        return $this->hasMany(Fee::class);
    }
}
