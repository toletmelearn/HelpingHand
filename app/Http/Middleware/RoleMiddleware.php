<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();

        // Super Admin Global Override
        if ($user->isSuperAdmin()) {
            if (!app()->runningUnitTests()) {
                activity()
                    ->causedBy($user)
                    ->log('super_admin_override_used');
            }
                
            return $next($request);
        }
        
        $userRoles = $user->roles->pluck('name')->toArray();
        
        // Check if user has any of the required roles directly or via hierarchy
        foreach ($roles as $role) {
            $parts = explode(',', $role);
            foreach ($parts as $part) {
                $part = trim($part);
                
                // Direct role check
                if (in_array($part, $userRoles)) {
                    return $next($request);
                }

                // Hierarchy check
                if ($this->hasPermission($user, $part)) {
                    return $next($request);
                }
            }
        }

        abort(403, 'Unauthorized access.');
    }

    /**
     * Check if user has permission based on role hierarchy
     */
    private function hasPermission($user, $requiredRole)
    {
        // Define role hierarchy
        $roleHierarchy = [
            'admin' => 3,
            'accountant' => 2,
            'clerk' => 2,
            'receptionist' => 1
        ];

        $userRoles = $user->roles->pluck('name')->toArray();
        
        foreach ($userRoles as $userRole) {
            if (isset($roleHierarchy[$userRole]) && isset($roleHierarchy[$requiredRole])) {
                if ($roleHierarchy[$userRole] >= $roleHierarchy[$requiredRole]) {
                    return true;
                }
            }
        }

        return false;
    }
}