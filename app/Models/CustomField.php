<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'module',
        'field_name',
        'field_type',
        'validation_rules',
        'is_required'
    ];

    protected $casts = [
        'validation_rules' => 'array',
        'is_required' => 'boolean',
    ];

    public function values()
    {
        return $this->hasMany(CustomFieldValue::class);
    }
}
