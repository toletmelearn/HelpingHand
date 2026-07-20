<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([
            \App\Http\Middleware\RouteErrorLogger::class,
            \App\Http\Middleware\TrackPerformance::class,
        ]);
        
        // Register role-based middleware
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'parent.auth' => \App\Http\Middleware\ParentAuth::class,
            'redirect.if.not.onboarded' => \App\Http\Middleware\RedirectIfNotOnboarded::class,
        ]);
        
        // Re-enable CSRF middleware
        // $middleware->remove([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function ($schedule) {
        $schedule->command('reminders:send-all')->daily();
        $schedule->command('reminders:retry-failed')->hourly();
        $schedule->call(function () {
            cache(['scheduler_heartbeat' => time()], now()->addHours(1));
        })->everyMinute();
    })->create();
