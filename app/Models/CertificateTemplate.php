<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateTemplate extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'type',
        'template_content',
        'template_variables',
        'is_default',
        'is_active',
        'created_by',
        'updated_by'
    ];
    
    protected $casts = [
        'template_variables' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    
    public static function getDefaultTemplate($type = null)
    {
        if ($type) {
            return static::where('type', $type)
                         ->where('is_default', true)
                         ->where('is_active', true)
                         ->first();
        }
        
        return static::where('is_default', true)
                     ->where('is_active', true)
                     ->first();
    }
    
    public static function getActiveTemplatesByType($type)
    {
        return static::where('type', $type)
                     ->where('is_active', true)
                     ->orderBy('name')
                     ->get();
    }
}
