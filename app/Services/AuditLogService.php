<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Log a model change (create/update/delete)
     */
    public function log($model, $action, $changedFields = [], $userId = null, $userType = null)
    {
        // Get user info if not provided
        if (!$userId && Auth::check()) {
            $userId = Auth::id();
            $userType = Auth::user()->roles->first()?->name ?? 'user';
        }

        // Log each changed field individually
        if (!empty($changedFields)) {
            foreach ($changedFields as $field => $values) {
                AuditLog::create([
                    'user_type' => $userType ?? 'unknown',
                    'user_id' => $userId ?? 0,
                    'model_type' => get_class($model),
                    'model_id' => $model->id,
                    'field_name' => $field,
                    'old_value' => $values['old'] ?? null,
                    'new_value' => $values['new'] ?? null,
                    'action' => $action,
                    'ip_address' => Request::ip(),
                    'user_agent' => Request::userAgent(),
                    'performed_at' => now(),
                ]);
            }
        } else {
            // Log the general action without specific field changes
            AuditLog::create([
                'user_type' => $userType ?? 'unknown',
                'user_id' => $userId ?? 0,
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'field_name' => '',
                'old_value' => '',
                'new_value' => '',
                'action' => $action,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'performed_at' => now(),
            ]);
        }
    }

    /**
     * Log a specific field change
     */
    public function logFieldChange($model, $field, $oldValue, $newValue, $userId = null, $userType = null)
    {
        if (!$userId && Auth::check()) {
            $userId = Auth::id();
            $userType = Auth::user()->roles->first()?->name ?? 'user';
        }

        AuditLog::create([
            'user_type' => $userType ?? 'unknown',
            'user_id' => $userId ?? 0,
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'field_name' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'action' => 'update',
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'performed_at' => now(),
        ]);
    }

    /**
     * Log model creation
     */
    public function logCreation($model, $userId = null, $userType = null)
    {
        $this->log($model, 'create', [], $userId, $userType);
    }

    /**
     * Log model update
     */
    public function logUpdate($model, $changedFields = [], $userId = null, $userType = null)
    {
        $this->log($model, 'update', $changedFields, $userId, $userType);
    }

    /**
     * Log model deletion
     */
    public function logDeletion($model, $userId = null, $userType = null)
    {
        $this->log($model, 'delete', [], $userId, $userType);
    }
}