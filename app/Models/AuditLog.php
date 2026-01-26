<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_type',
        'user_id',
        'model_type',
        'model_id',
        'field_name',
        'old_value',
        'new_value',
        'action',
        'ip_address',
        'user_agent',
        'performed_at',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
    ];

    // Scopes
    public function scopeForModel($query, $modelType, $modelId)
    {
        return $query->where('model_type', $modelType)->where('model_id', $modelId);
    }

    public function scopeForUser($query, $userId, $userType = null)
    {
        $query = $query->where('user_id', $userId);
        if ($userType) {
            $query->where('user_type', $userType);
        }
        return $query;
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('performed_at', [$startDate, $endDate]);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeForField($query, $fieldName)
    {
        return $query->where('field_name', $fieldName);
    }

    // Helper methods
    public function getReadableAction(): string
    {
        $actions = [
            'create' => 'Created',
            'update' => 'Updated',
            'delete' => 'Deleted',
            'restore' => 'Restored',
        ];

        return $actions[$this->action] ?? ucfirst($this->action);
    }

    public function isCreateAction(): bool
    {
        return $this->action === 'create';
    }

    public function isUpdateAction(): bool
    {
        return $this->action === 'update';
    }

    public function isDeleteAction(): bool
    {
        return $this->action === 'delete';
    }
}