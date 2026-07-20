<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportCardTemplate extends Model
{
    protected $fillable = [
        'name',
        'layout_config',
        'scholastic_sections',
        'is_active',
    ];

    protected $casts = [
        'layout_config' => 'array',
        'scholastic_sections' => 'array',
        'is_active' => 'boolean',
    ];
}
