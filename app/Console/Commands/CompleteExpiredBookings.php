<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CompleteExpiredBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:complete-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark expired bookings as completed when date/time has passed';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 🆕 Ottieni tutte le prenotazioni "confirmed" che sono scadute
        $now = Carbon::now();

        // Carica solo le prenotazioni confermate di oggi o dei giorni passati.
        // La scadenza effettiva viene calcolata usando la durata del servizio.
        $bookings = Booking::with('service')
            ->where('status', 'confirmed')
            ->whereDate('date', '<=', $now->toDateString())
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            if (!$booking->service) {
                continue;
            }

            $bookingDate = $booking->date instanceof Carbon
                ? $booking->date->format('Y-m-d')
                : Carbon::parse($booking->date)->format('Y-m-d');

            $bookingEnd = Carbon::parse("{$bookingDate} {$booking->time}")
                ->addMinutes((int) $booking->service->duration);

            if ($bookingEnd->lte($now)) {
                $booking->update(['status' => 'completed']);
                $count++;
            }
        }

        $this->info("✅ Marked $count expired bookings as completed");
        return 0;
    }
}
