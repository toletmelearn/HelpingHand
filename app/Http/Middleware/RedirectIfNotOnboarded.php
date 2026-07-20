<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AdminConfiguration;

class RedirectIfNotOnboarded
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Bypass onboarding redirect in testing environment unless explicitly enforced
        if (app()->runningUnitTests() && !config('app.enforce_onboarding_check_in_tests', false)) {
            return $next($request);
        }

        // Check if the current route is the setup wizard itself or logout
        if ($request->routeIs('admin.setup-wizard*') || $request->routeIs('logout')) {
            return $next($request);
        }

        // If user is authenticated and is an admin (or super admin), check onboarding status
        if (Auth::check()) {
            $user = Auth::user();
            $isAdmin = $user->isSuperAdmin() || $user->roles->pluck('name')->contains('admin');
            
            if ($isAdmin) {
                $isOnboarded = (bool) AdminConfiguration::get('general', 'is_onboarded', false);

                if (!$isOnboarded) {
                    return redirect()->route('admin.setup-wizard.index');
                }
            }
        }

        return $next($request);
    }
}
