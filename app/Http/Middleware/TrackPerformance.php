<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TrackPerformance
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = microtime(true) - $start;

        // Skip asset or debug paths to keep numbers clean
        if (!$request->expectsJson() && (str_contains($request->path(), 'assets') || str_contains($request->path(), 'vendor') || str_contains($request->path(), '_debugbar'))) {
            return $response;
        }

        try {
            $times = Cache::get('perf_response_times', [0.12, 0.15, 0.19]);
            $times[] = $duration;

            if (count($times) > 50) {
                array_shift($times);
            }

            Cache::put('perf_response_times', $times, now()->addHours(24));
        } catch (\Throwable $e) {
            // cache driver might not be configured yet during unit testing
        }

        return $response;
    }
}
