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
        $expiredBookings = Booking::where('status', 'confirmed')
            ->where(function ($query) {
                $query->whereRaw('CONCAT(date, \' \', time) <= ?', [Carbon::now()->format('Y-m-d H:i')])
                    ->orWhereRaw('DATE(date) < ?', [Carbon::now()->format('Y-m-d')]);
            })
            ->get();

        $count = 0;
        foreach ($expiredBookings as $booking) {
            $booking->update(['status' => 'completed']);
            $count++;
        }

        $this->info("✅ Marked $count expired bookings as completed");
        return 0;
    }
}
