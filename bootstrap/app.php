<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register Spatie Permission Middlewares
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // Optional: Add API middleware group customization
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // 🎯 AUTO-COMPLETE BOOKING SCHEDULING
        $schedule->command('bookings:auto-complete')
                 ->everyFifteenMinutes()
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/auto-complete-bookings.log'));
        
        // Alternative scheduling options:
        // $schedule->command('bookings:auto-complete')->hourly();
        // $schedule->command('bookings:auto-complete')->dailyAt('02:00');
        // $schedule->command('bookings:auto-complete')->twiceDaily(9, 21);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();