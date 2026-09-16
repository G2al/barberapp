<?php

namespace App\Providers;

use App\Models\Booking;
use App\Observers\BookingObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Booking::observe(BookingObserver::class);

        // Register Telegram notification channel
        \Illuminate\Support\Facades\Notification::extend('telegram', function ($app) {
            return new \App\Channels\TelegramChannel;
        });
    }
}
