<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\LoyaltyService;
use Illuminate\Console\Command;

class SyncLoyaltyPoints extends Command
{
    protected $signature = 'loyalty:sync-completed {--user_id= : Sync only one user}';

    protected $description = 'Create missing loyalty points for completed bookings';

    public function handle(LoyaltyService $loyalty): int
    {
        $count = 0;

        Booking::query()
            ->with(['service', 'user'])
            ->where('status', 'completed')
            ->when($this->option('user_id'), fn ($query, $userId) => $query->where('user_id', $userId))
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use ($loyalty, &$count): void {
                foreach ($bookings as $booking) {
                    $loyalty->awardCompletedBooking($booking);
                    $count++;
                }
            });

        $this->info("Synced loyalty points for {$count} completed bookings.");

        return self::SUCCESS;
    }
}
