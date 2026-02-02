<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RouteErrorLogger
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  ...$guards
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        try {
            return $next($request);
        } catch (NotFoundHttpException $e) {
            $this->logRouteError($request, '404', $e);
            return $this->renderCustom404($request);
        } catch (AccessDeniedHttpException $e) {
            $this->logRouteError($request, '403', $e);
            return $this->renderCustom403($request);
        } catch (\Exception $e) {
            // Log other route-related exceptions
            if (str_contains($e->getMessage(), 'Route') || str_contains($e->getMessage(), 'route')) {
                $this->logRouteError($request, '500', $e);
            }
            throw $e; // Re-throw non-route exceptions
        }
    }

    /**
     * Log route errors to database and file
     */
    private function logRouteError(Request $request, string $errorType, \Exception $exception)
    {
        $user = Auth::user();
        
        $logData = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'error_type' => $errorType,
            'exception_message' => $exception->getMessage(),
            'exception_trace' => $exception->getTraceAsString(),
            'user_id' => $user ? $user->id : null,
            'user_role' => $user ? ($user->roles->first()->name ?? 'user') : null,
            'timestamp' => now()
        ];

        // Log to file
        Log::channel('route_errors')->error("Route Error [{$errorType}]: " . $exception->getMessage(), $logData);

        // Future: Log to database table for analytics
        // This can be implemented later with a migration
    }

    /**
     * Render custom 404 page
     */
    private function renderCustom404(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'The requested resource was not found.',
                'timestamp' => now()
            ], 404);
        }

        // Try to render custom 404 view, fallback to simple response
        try {
            return response()->view('errors.404', [
                'url' => $request->fullUrl(),
                'timestamp' => now()
            ], 404);
        } catch (\Exception $e) {
            return response('Page not found', 404);
        }
    }

    /**
     * Render custom 403 page
     */
    private function renderCustom403(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'You do not have permission to access this resource.',
                'timestamp' => now()
            ], 403);
        }

        // Try to render custom 403 view, fallback to simple response
        try {
            return response()->view('errors.403', [
                'url' => $request->fullUrl(),
                'timestamp' => now()
            ], 403);
        } catch (\Exception $e) {
            return response('Access forbidden', 403);
        }
    }
}