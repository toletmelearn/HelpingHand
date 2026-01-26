<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_type',
        'field_name',
        'role',
        'permission_level',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Scopes
    public function scopeForModel($query, $modelType)
    {
        return $query->where('model_type', $modelType);
    }

    public function scopeForRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeForField($query, $fieldName)
    {
        return $query->where('field_name', $fieldName);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    // Helper methods
    public function isEditable(): bool
    {
        return $this->permission_level === 'editable';
    }

    public function isReadOnly(): bool
    {
        return $this->permission_level === 'read_only';  // Updated to match migration
    }

    public function isHidden(): bool
    {
        return $this->permission_level === 'hidden';
    }

    public function getReadablePermissionLevel(): string
    {
        $levels = [
            'editable' => 'Editable',
            'read_only' => 'Read-Only',  // Updated to match migration
            'hidden' => 'Hidden',
        ];

        return $levels[$this->permission_level] ?? ucfirst(str_replace('_', ' ', $this->permission_level));
    }

    // Static methods to get permissions for a specific role and model
    public static function getPermissionsForRole(string $modelType, string $role): array
    {
        return self::forModel($modelType)
                   ->forRole($role)
                   ->active()
                   ->pluck('permission_level', 'field_name')
                   ->toArray();
    }

    public static function getFieldPermission(string $modelType, string $fieldName, string $role): ?self
    {
        return self::forModel($modelType)
                   ->forField($fieldName)
                   ->forRole($role)
                   ->active()
                   ->first();
    }
}