<?php

namespace App\Observers;

use App\Models\Booking;
use App\Services\LoyaltyService;

class BookingObserver
{
    public function updated(Booking $booking): void
    {
        if ($booking->wasChanged('status') && $booking->status === 'completed') {
            app(LoyaltyService::class)->awardCompletedBooking($booking);
        }
    }
}
