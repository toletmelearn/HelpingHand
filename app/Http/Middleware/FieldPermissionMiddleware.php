<?php

namespace App\Http\Middleware;

use App\Models\FieldPermission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class FieldPermissionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $modelType): Response
    {
        // Get the authenticated user's role
        $user = Auth::user();
        $roleId = $user ? $user->roles->first()?->name : 'guest';

        // If it's a GET request, just continue (read operations)
        if (in_array($request->method(), ['GET', 'HEAD'])) {
            return $next($request);
        }

        // For POST/PUT/PATCH/DELETE, check field permissions
        $requestData = $request->all();
        $forbiddenFields = [];

        foreach ($requestData as $field => $value) {
            // Skip common fields that shouldn't be checked
            if (in_array($field, ['_token', '_method', 'password', 'password_confirmation'])) {
                continue;
            }

            // Check if there's a field permission defined
            $permission = FieldPermission::where('model_type', $modelType)
                                         ->where('field_name', $field)
                                         ->where('role', $roleId)
                                         ->active()
                                         ->first();

            if ($permission) {
                // If the field is hidden or read-only, don't allow modification
                if ($permission->isHidden() || $permission->isReadOnly()) {
                    $forbiddenFields[] = $field;
                }
            }
        }

        if (!empty($forbiddenFields)) {
            // If there are forbidden fields, remove them from the request
            foreach ($forbiddenFields as $field) {
                $request->offsetUnset($field);
            }
        }

        return $next($request);
    }
}