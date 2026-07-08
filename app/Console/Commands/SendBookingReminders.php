<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingReminderService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendBookingReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Invia reminder prenotazioni a 24 ore, 3 ore e meno di 1 ora';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        $reminders = app(BookingReminderService::class);

        // Cerca le prenotazioni confermate future; il servizio decide quale reminder inviare.
        $bookings = Booking::where('status', 'confirmed')
            ->whereDate('date', '>=', $now->format('Y-m-d'))
            ->get();

        $this->info("Prenotazioni trovate nel DB: {$bookings->count()}");
        $sent = 0;

        foreach ($bookings as $booking) {
            try {
                $type = $reminders->sendDueReminder($booking, $now);

                if ($type !== null) {
                    $this->info("Invio reminder {$type} per prenotazione #{$booking->id}");
                    $sent++;
                }
            } catch (\Exception $e) {
                $this->error("Errore prenotazione #{$booking->id}: {$e->getMessage()}");
            }
        }

        $this->info("Reminder inviati: {$sent}");
    }
}
