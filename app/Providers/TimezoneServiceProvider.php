<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TimezoneServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Set default timezone for Carbon
        date_default_timezone_set(config('app.timezone', 'Asia/Jakarta'));

        // Set database timezone for MySQL connections
        try {
            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement("SET time_zone = '+07:00'");
            }
        } catch (\Exception $e) {
            // Log error but don't break the application
            \Log::warning('Failed to set database timezone: ' . $e->getMessage());
        }

        // Override the now() helper to always use the correct timezone
        $this->app->singleton('timezone.now', function () {
            return Carbon::now()->setTimezone(config('app.timezone', 'Asia/Jakarta'));
        });
    }
}
