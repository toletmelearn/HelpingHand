<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ApiAccessControl
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Rate limiting
        $this->rateLimit($request);
        
        // API access logging
        $this->logApiAccess($request);
        
        // Role-based access control
        if (!$this->authorizeRequest($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Insufficient permissions.',
                'timestamp' => now()->toISOString()
            ], 403);
        }
        
        return $next($request);
    }
    
    /**
     * Apply rate limiting to API requests
     */
    private function rateLimit(Request $request)
    {
        $key = $this->resolveRequestSignature($request);
        
        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts())) {
            $retryAfter = RateLimiter::availableIn($key);
            
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $retryAfter,
                'timestamp' => now()->toISOString()
            ], 429)->header('Retry-After', $retryAfter);
        }
        
        RateLimiter::hit($key, $this->decayMinutes() * 60);
    }
    
    /**
     * Resolve request signature for rate limiting
     */
    private function resolveRequestSignature(Request $request)
    {
        $userIdentifier = Auth::check() ? Auth::id() : $request->ip();
        return 'api:' . $userIdentifier . ':' . $request->path();
    }
    
    /**
     * Maximum number of attempts allowed
     */
    private function maxAttempts()
    {
        return Auth::check() ? 60 : 10; // 60 for authenticated, 10 for unauthenticated
    }
    
    /**
     * Decay time in minutes
     */
    private function decayMinutes()
    {
        return 1; // 1 minute window
    }
    
    /**
     * Log API access for audit purposes
     */
    private function logApiAccess(Request $request)
    {
        // Log API access to database or file
        // This could integrate with the existing AuditLog system
        \Log::info('API Access', [
            'user_id' => Auth::check() ? Auth::id() : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'timestamp' => now()->toISOString()
        ]);
    }
    
    /**
     * Authorize the request based on user roles and permissions
     */
    private function authorizeRequest(Request $request)
    {
        // Allow all requests for now - in a real implementation,
        // you would check user roles and permissions here
        // For example:
        // 
        // if (Auth::check()) {
        //     $user = Auth::user();
        //     
        //     // Teachers can only access their own data
        //     if ($user->hasRole('teacher')) {
        //         return $this->authorizeTeacherAccess($request, $user);
        //     }
        //     
        //     // Parents can only access their children's data
        //     if ($user->hasRole('parent')) {
        //         return $this->authorizeParentAccess($request, $user);
        //     }
        //     
        //     // Students can only access their own data
        //     if ($user->hasRole('student')) {
        //         return $this->authorizeStudentAccess($request, $user);
        //     }
        // }
        
        return true; // Allow all access for demonstration
    }
}