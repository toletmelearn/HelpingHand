<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_name',
        'version',
        'file_path',
        'schema_definition',
        'is_active'
    ];

    protected $casts = [
        'schema_definition' => 'array',
        'is_active' => 'boolean',
    ];
}
