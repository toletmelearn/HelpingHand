<?php

namespace App\Traits;

use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    /**
     * Boot the trait
     */
    public static function bootAuditable()
    {
        static::creating(function ($model) {
            $auditLogService = new AuditLogService();
            $auditLogService->logCreation($model);
        });

        static::updating(function ($model) {
            $auditLogService = new AuditLogService();
            
            // Get the original attributes before the update
            $original = $model->getOriginal();
            $changes = [];

            foreach ($model->getDirty() as $attribute => $newValue) {
                $oldValue = $original[$attribute] ?? null;
                
                // Only log if the value actually changed
                if ($oldValue != $newValue) {
                    $changes[$attribute] = [
                        'old' => $oldValue,
                        'new' => $newValue
                    ];
                }
            }

            if (!empty($changes)) {
                $auditLogService->logUpdate($model, $changes);
            }
        });

        static::deleting(function ($model) {
            $auditLogService = new AuditLogService();
            $auditLogService->logDeletion($model);
        });
    }
}