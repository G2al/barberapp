<?php

namespace App\Providers;

use App\Services\Ai\SalonContactAction;
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
            $rateLimitResponse = function (Request $request, array $headers) {
                $retryAfter = max(1, (int) ($headers['Retry-After'] ?? 60));
                $message = $retryAfter <= 60
                    ? 'Stai inviando messaggi molto velocemente. Attendi qualche secondo e riprova.'
                    : 'Hai raggiunto il limite temporaneo della chat. Riprova piu tardi.';
                $payload = [
                    'status' => false,
                    'message' => $message,
                    'error_code' => 'ai_rate_limited',
                    'retry_after' => $retryAfter,
                ];

                if ($action = app(SalonContactAction::class)->make()) {
                    $payload['action'] = $action;
                }

                return response()->json($payload, 429, $headers);
            };

            return [
                Limit::perMinute(max(1, (int) config('ai.rate_limits.minute', 10)))
                    ->by($key.':minute')
                    ->response($rateLimitResponse),
                Limit::perHour(max(1, (int) config('ai.rate_limits.hour', 60)))
                    ->by($key.':hour')
                    ->response($rateLimitResponse),
                Limit::perDay(max(1, (int) config('ai.rate_limits.day', 100)))
                    ->by($key.':day')
                    ->response($rateLimitResponse),
            ];
        });
    }
}
