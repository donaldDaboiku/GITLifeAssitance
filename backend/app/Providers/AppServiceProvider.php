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
        RateLimiter::for('ai', function (Request $request) {
            $perMinute = max(1, (int) config('ai.rate_per_minute', 10));

            return Limit::perMinute($perMinute)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
