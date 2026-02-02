<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Translation extends Model
{
    use HasFactory;

    protected $fillable = [
        'language_id',
        'key',
        'value',
        'module',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    // Relationships
    public function language()
    {
        return $this->belongsTo(Language::class);
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeByLanguage($query, $languageId)
    {
        return $query->where('language_id', $languageId);
    }

    public function scopeByKey($query, $key)
    {
        return $query->where('key', $key);
    }

    public function scopeByModule($query, $module)
    {
        return $query->where('module', $module);
    }

    // Static Methods
    public static function translate($key, $languageCode = null, $default = null)
    {
        $languageCode = $languageCode ?? app()->getLocale();
        $default = $default ?? $key;
        
        $language = Language::where('code', $languageCode)->first();
        
        if (!$language) {
            return $default;
        }
        
        $translation = self::published()
            ->byLanguage($language->id)
            ->byKey($key)
            ->first();
            
        return $translation ? $translation->value : $default;
    }
}
