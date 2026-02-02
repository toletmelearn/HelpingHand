<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class AdminConfiguration extends Model
{
    use Auditable;

    protected $fillable = [
        'module',
        'key',
        'value',
        'type',
        'label',
        'description',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'value' => 'array', // Automatically handle JSON encoding/decoding
    ];

    // Relationships
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForModule($query, $module)
    {
        return $query->where('module', $module);
    }

    public function scopeForKey($query, $key)
    {
        return $query->where('key', $key);
    }

    // Helper methods
    public function getValue()
    {
        if ($this->type === 'boolean') {
            return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
        } elseif ($this->type === 'integer') {
            return (int) $this->value;
        } elseif ($this->type === 'json') {
            return json_decode($this->value, true);
        }
        
        return $this->value;
    }

    public function setValue($value)
    {
        if ($this->type === 'json' && !is_string($value)) {
            $value = json_encode($value);
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        }
        
        $this->value = $value;
        return $this;
    }

    public static function get($module, $key, $default = null)
    {
        $config = self::where('module', $module)
                    ->where('key', $key)
                    ->active()
                    ->first();
        
        return $config ? $config->getValue() : $default;
    }

    public static function set($module, $key, $value, $type = 'boolean')
    {
        $config = self::updateOrCreate(
            ['module' => $module, 'key' => $key],
            [
                'value' => is_bool($value) ? ($value ? '1' : '0') : $value,
                'type' => $type,
                'is_active' => true,
            ]
        );
        
        return $config;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }
}
