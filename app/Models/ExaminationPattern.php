<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaminationPattern extends Model
{
    protected $fillable = [
        'name',
        'code',
        'pattern_config',
        'is_default',
        'is_active',
        'sort_order',
        'description',
    ];
    
    protected $casts = [
        'pattern_config' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
