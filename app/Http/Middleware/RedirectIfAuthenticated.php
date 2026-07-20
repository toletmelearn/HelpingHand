<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
        foreach ($guards as $guard) {

            if (Auth::guard($guard)->check()) {

                // ADMIN LOGIN
                if ($guard == 'web') {
                    return redirect('/admin/dashboard');
                }

                // PARENT LOGIN
                if ($guard == 'parent') {
                    return redirect('/parent/dashboard');
                }

                // TEACHER LOGIN
                if ($guard == 'teacher') {
                    return redirect('/teacher/dashboard');
                }
            }
        }

        return $next($request);
    }
}