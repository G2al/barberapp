<?php

namespace App\Providers;

use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;
use App\Models\Booking;
use App\Observers\BookingObserver;

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
            return new \App\Channels\TelegramChannel();
        });
    }
}
