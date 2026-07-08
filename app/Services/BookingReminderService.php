<?php

namespace App\Services;

use App\Models\Booking;
use App\Notifications\BookingReminderNotification;
use Carbon\Carbon;

class BookingReminderService
{
    public function sendDueReminder(Booking $booking, ?Carbon $now = null): ?string
    {
        $now ??= Carbon::now();
        $bookingDateTime = $this->bookingDateTime($booking);
        $minutesUntilBooking = $now->diffInMinutes($bookingDateTime, false);

        if ($minutesUntilBooking <= 0) {
            return null;
        }

        if ($minutesUntilBooking <= 60 && !$booking->reminder_1h_sent) {
            return $this->send($booking, '1h', 'reminder_1h_sent');
        }

        if ($minutesUntilBooking <= 180 && !$booking->reminder_3h_sent) {
            return $this->send($booking, '3h', 'reminder_3h_sent');
        }

        if ($minutesUntilBooking <= 1440 && !$booking->reminder_24h_sent) {
            return $this->send($booking, '24h', 'reminder_24h_sent');
        }

        return null;
    }

    public function bookingDateTime(Booking $booking): Carbon
    {
        $date = $booking->date instanceof Carbon
            ? $booking->date->format('Y-m-d')
            : Carbon::parse($booking->date)->format('Y-m-d');

        $time = substr((string) $booking->time, 0, 5);

        return Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}");
    }

    private function send(Booking $booking, string $type, string $flag): string
    {
        $booking->loadMissing(['user', 'staff', 'service']);
        $booking->user->notify(new BookingReminderNotification($booking, $type));
        $booking->forceFill([$flag => true])->save();

        return $type;
    }
}
