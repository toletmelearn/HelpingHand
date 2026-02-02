<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingSystem extends Model
{
    protected $fillable = [
        'name',
        'grade',
        'min_percentage',
        'max_percentage',
        'grade_points',
        'description',
        'is_active',
        'order',
    ];
    
    protected $casts = [
        'min_percentage' => 'decimal:2',
        'max_percentage' => 'decimal:2',
        'grade_points' => 'decimal:2',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];
}
