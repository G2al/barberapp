<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Notifications\BookingReminderNotification;
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
    protected $description = 'Invia reminder notifiche 3 ore prima della prenotazione';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        // Cerca TUTTE le prenotazioni confermate senza reminder
        $bookings = Booking::where('status', 'confirmed')
            ->where('reminder_sent', false)
            ->get();

        $this->info("Prenotazioni trovate nel DB: {$bookings->count()}");
        $sent = 0;

        foreach ($bookings as $booking) {
            try {
                // Converti date a Y-m-d format (è una Carbon instance a causa del casting)
                $dateString = $booking->date instanceof \Carbon\Carbon ? $booking->date->format('Y-m-d') : $booking->date;

                // Parse la data e ora della prenotazione
                $bookingDateTime = Carbon::createFromFormat('Y-m-d H:i:s', "$dateString {$booking->time}");

                // Calcola minuti fino alla prenotazione
                $minutesUntilBooking = $now->diffInMinutes($bookingDateTime);

                // Invia reminder quando mancano ESATTAMENTE 60 minuti (±5 minuti di tolleranza)
                if ($minutesUntilBooking >= 55 && $minutesUntilBooking <= 65) {

                    $this->info("Invio reminder per prenotazione #{$booking->id}: mancano {$minutesUntilBooking} minuti");

                    $booking->user->notify(new BookingReminderNotification($booking));
                    $booking->update(['reminder_sent' => true]);
                    $sent++;
                }
            } catch (\Exception $e) {
                $this->error("Errore prenotazione #{$booking->id}: {$e->getMessage()}");
            }
        }

        $this->info("Reminders inviati: {$sent}");
    }
}
