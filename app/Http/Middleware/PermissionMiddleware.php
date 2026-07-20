<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fine-grained duty gate on top of RoleMiddleware's coarse role gate --
 * checks Auth::user()->hasPermission() against the role's assignable
 * permissions (Manage Permissions screen), rather than a hardcoded role
 * name. A role must be explicitly granted the permission there; admin gets
 * every permission automatically via PermissionSeeder, so this never locks
 * out the admin role itself.
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        foreach ($permissions as $permission) {
            foreach (explode(',', $permission) as $name) {
                if ($user->hasPermission(trim($name))) {
                    return $next($request);
                }
            }
        }

        abort(403, 'You do not have permission to access this feature. Ask an administrator to grant it under Manage Permissions.');
    }
}
