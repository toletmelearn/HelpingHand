<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LanguageSetting extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'locale',
        'name',
        'flag',
        'is_default',
        'is_active',
        'created_by',
        'updated_by',
    ];
    
    protected $casts = [
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
    
    public static function getDefaultLanguage()
    {
        return static::where('is_default', true)
                     ->where('is_active', true)
                     ->first();
    }
    
    public static function getActiveLanguages()
    {
        return static::where('is_active', true)
                     ->orderBy('name')
                     ->get();
    }
    
    public function isActive(): bool
    {
        return $this->is_active;
    }
    
    public function isDefault(): bool
    {
        return $this->is_default;
    }
    
    public function getFlagUrlAttribute(): string
    {
        if ($this->flag) {
            return asset('images/flags/' . $this->flag);
        }
        
        // Default to a generic flag if none is set
        return asset('images/flags/default.png');
    }
}
