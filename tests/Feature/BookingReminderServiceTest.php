<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\BookingReminderNotification;
use App\Services\BookingReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_only_one_hour_reminder_is_enabled_by_default(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-08-05 10:00:00'));

        $user = User::factory()->create([
            'surname' => 'Cliente',
            'phone' => '3330000001',
        ]);
        $staff = Staff::create([
            'first_name' => 'Salvatore',
            'last_name' => 'Napp',
            'is_active' => true,
        ]);
        $service = Service::create([
            'name' => 'Taglio',
            'price' => 10,
            'duration' => 30,
            'is_active' => true,
        ]);
        $reminders = app(BookingReminderService::class);

        $booking24h = $this->booking($user, $staff, $service, Carbon::now()->addHours(23));
        $booking3h = $this->booking($user, $staff, $service, Carbon::now()->addHours(2));
        $booking1h = $this->booking($user, $staff, $service, Carbon::now()->addMinutes(50));

        $this->assertNull($reminders->sendDueReminder($booking24h));
        $this->assertNull($reminders->sendDueReminder($booking3h));
        $this->assertSame('1h', $reminders->sendDueReminder($booking1h));

        $this->assertFalse($booking1h->fresh()->reminder_24h_sent);
        $this->assertFalse($booking1h->fresh()->reminder_3h_sent);
        $this->assertTrue($booking1h->fresh()->reminder_1h_sent);
        Notification::assertSentTo(
            $user,
            BookingReminderNotification::class,
            fn (BookingReminderNotification $notification): bool => $notification->type === '1h',
        );

    }

    private function booking(User $user, Staff $staff, Service $service, Carbon $dateTime): Booking
    {
        return Booking::create([
            'user_id' => $user->id,
            'staff_id' => $staff->id,
            'service_id' => $service->id,
            'date' => $dateTime->toDateString(),
            'time' => $dateTime->format('H:i'),
            'status' => 'confirmed',
        ]);
    }
}
