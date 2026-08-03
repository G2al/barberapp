<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Definizione del rate limiter "api"
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('ai', function (Request $request) {
            $key = $request->user()
                ? 'user:'.$request->user()->getAuthIdentifier()
                : 'ip:'.$request->ip();

            return [
                Limit::perMinute(max(1, (int) config('ai.rate_limits.minute', 3)))->by($key.':minute'),
                Limit::perHour(max(1, (int) config('ai.rate_limits.hour', 30)))->by($key.':hour'),
                Limit::perDay(max(1, (int) config('ai.rate_limits.day', 100)))->by($key.':day'),
            ];
        });
    }
}
