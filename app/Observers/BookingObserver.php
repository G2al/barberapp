<?php

namespace App\Observers;

use App\Models\Booking;
use App\Notifications\BookingCancelledTelegramNotification;
use App\Services\BookingWaitlistService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class BookingObserver
{
    public function updated(Booking $booking): void
    {
        if (! $booking->wasChanged('status') || $booking->status !== 'cancelled') {
            return;
        }

        $bookingId = $booking->getKey();

        DB::afterCommit(function () use ($bookingId) {
            $cancelledBooking = Booking::find($bookingId);
            if ($cancelledBooking) {
                Notification::route('telegram', config('services.telegram.chat_id'))
                    ->notify(new BookingCancelledTelegramNotification($cancelledBooking));

                app(BookingWaitlistService::class)->promoteFor($cancelledBooking);
            }
        });
    }
}
