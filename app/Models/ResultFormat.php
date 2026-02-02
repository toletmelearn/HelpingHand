<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResultFormat extends Model
{
    protected $fillable = [
        'name',
        'code',
        'template_html',
        'fields',
        'is_default',
        'is_active',
        'sort_order',
        'description',
    ];
    
    protected $casts = [
        'fields' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
