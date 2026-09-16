<?php

namespace App\Observers;

use App\Models\Booking;
use App\Services\BookingWaitlistService;
use Illuminate\Support\Facades\DB;

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
                app(BookingWaitlistService::class)->promoteFor($cancelledBooking);
            }
        });
    }
}
