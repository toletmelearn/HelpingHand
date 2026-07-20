<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class ExamHeadMiddleware
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
        // Check if authenticated user is an admin (they can access everything)
        if (Auth::check()) {
            $user = Auth::user();
            // Check using the role field directly
            if ($user->role === 'admin') {
                return $next($request);
            }
        }
        
        // Check if teacher is logged in and is an exam head
        $teacherLogin = Auth::guard('teacher')->user();
        
        if ($teacherLogin && $teacherLogin->teacher) {
            $teacher = $teacherLogin->teacher;
            
            $isExamHead = $teacher->isExamHead();
            
            if ($isExamHead) {
                return $next($request);
            }
        }
        
        // If not authorized, redirect back with error
        return redirect()->back()->with('error', 'Access denied. Only exam heads or administrators can access this section.');
    }
}