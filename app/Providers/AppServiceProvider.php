<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Match the legacy Express limiter: 500 requests per 15 minutes per client IP.
        RateLimiter::for('cmms-api', fn (Request $request) => [
            Limit::perMinutes(15, 500)->by($request->ip()),
        ]);
    }
}
