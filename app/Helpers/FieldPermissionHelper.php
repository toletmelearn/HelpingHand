<?php

namespace App\Helpers;

use App\Models\FieldPermission;
use App\Models\ClassTeacherAssignment;
use Illuminate\Support\Facades\Auth;

class FieldPermissionHelper
{
    /**
     * Check if the authenticated user can edit a specific field for a model
     */
    public static function canEditField(string $modelType, string $fieldName, $model = null): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Get user role
        $userRole = $user->roles->first()?->name ?? 'guest';

        // Check if there's a specific field permission
        $permission = FieldPermission::where('model_type', $modelType)
                                     ->where('field_name', $fieldName)
                                     ->where('role', $userRole)
                                     ->active()
                                     ->first();

        if ($permission) {
            return $permission->isEditable();
        }

        // Default behavior: allow if no specific permission is defined
        return true;
    }

    /**
     * Check if the authenticated user can view a specific field for a model
     */
    public static function canViewField(string $modelType, string $fieldName, $model = null): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Get user role
        $userRole = $user->roles->first()?->name ?? 'guest';

        // Check if there's a specific field permission
        $permission = FieldPermission::where('model_type', $modelType)
                                     ->where('field_name', $fieldName)
                                     ->where('role', $userRole)
                                     ->active()
                                     ->first();

        if ($permission) {
            return !$permission->isHidden();
        }

        // Default behavior: allow if no specific permission is defined
        return true;
    }

    /**
     * Get the permission level for a specific field
     */
    public static function getFieldPermissionLevel(string $modelType, string $fieldName, string $userRole): string
    {
        $permission = FieldPermission::where('model_type', $modelType)
                                     ->where('field_name', $fieldName)
                                     ->where('role', $userRole)
                                     ->active()
                                     ->first();

        if ($permission) {
            return $permission->permission_level;
        }

        // Default to editable if no specific permission
        return 'editable';
    }

    /**
     * Check if user is a class teacher for the relevant class
     */
    public static function isClassTeacherForModel($user, $model): bool
    {
        if (!$user || !$model) {
            return false;
        }

        // Assuming the model has a way to determine the class/group it belongs to
        // This depends on the specific model structure
        
        // For students, check if user is class teacher for student's class
        if (strtolower(class_basename($model)) === 'student' && isset($model->assigned_class)) {
            return $user->isClassTeacherForClass($model->assigned_class);
        }
        
        // For other models, implement as needed
        return false;
    }

    /**
     * Filter model attributes based on user permissions
     */
    public static function filterAttributesByPermissions($model, array $attributes, $user = null): array
    {
        if (!$user) {
            $user = Auth::user();
        }

        if (!$user) {
            return [];
        }

        $modelType = strtolower(class_basename($model));
        $userRole = $user->roles->first()?->name ?? 'guest';
        $filteredAttributes = [];

        foreach ($attributes as $field => $value) {
            $permissionLevel = self::getFieldPermissionLevel($modelType, $field, $userRole);

            if ($permissionLevel !== 'hidden') {
                $filteredAttributes[$field] = $value;
            }
        }

        return $filteredAttributes;
    }
}