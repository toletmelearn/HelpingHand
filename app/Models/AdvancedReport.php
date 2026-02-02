<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdvancedReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'module',
        'filters',
        'columns',
        'chart_type',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'filters' => 'array',
        'columns' => 'array',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByModule($query, $module)
    {
        return $query->where('module', $module);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
