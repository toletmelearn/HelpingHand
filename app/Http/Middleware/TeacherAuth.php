<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeacherAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::guard('teacher')->check()) {
            return redirect()->route('teacher.login');
        }

        $teacherLogin = Auth::guard('teacher')->user();

        if (
            $teacherLogin->force_password_change
            && !$request->routeIs('teacher.password.change')
            && !$request->routeIs('teacher.password.update')
            && !$request->routeIs('teacher.logout')
        ) {
            return redirect()->route('teacher.password.change')
                ->with('warning', 'You must change your password before continuing.');
        }

        return $next($request);
    }
}
