<?php

namespace Tests\Feature;

use App\Models\Availability;
use App\Models\Booking;
use App\Models\BookingWaitlistEntry;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\WaitlistBookingAssignedNotification;
use App\Services\BookingWaitlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingWaitlistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-16 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function customer(): User
    {
        return User::factory()->create([
            'surname' => 'Test',
            'phone' => fake()->unique()->numerify('333#######'),
        ]);
    }

    private function setupSlot(): array
    {
        $staff = Staff::create([
            'first_name' => 'Gabriele',
            'last_name' => 'Del Piano',
            'role' => 'barber',
            'is_active' => true,
            'uses_salon_hours' => true,
        ]);
        $service = Service::create([
            'name' => 'Taglio',
            'price' => 20,
            'duration' => 30,
            'is_active' => true,
        ]);
        $staff->services()->attach($service->id);
        Availability::create([
            'weekday' => 4,
            'slot_type' => 'morning',
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'is_active' => true,
        ]);

        return [$staff, $service];
    }

    private function occupiedBooking(Staff $staff, Service $service, User $user): Booking
    {
        return Booking::create([
            'user_id' => $user->id,
            'staff_id' => $staff->id,
            'service_id' => $service->id,
            'date' => '2026-09-17',
            'time' => '15:00:00',
            'status' => 'confirmed',
        ]);
    }

    public function test_customer_can_join_waitlist_for_an_occupied_slot_and_cancel_membership(): void
    {
        [$staff, $service] = $this->setupSlot();
        $this->occupiedBooking($staff, $service, $this->customer());
        $availability = $this->getJson('/api/availability/'.$staff->id.'?date=2026-09-17&serviceId='.$service->id.'&include_waitlist=1');
        $availability->assertOk();
        $this->assertContains('15:00', $availability->json('waitlist_slots'));
        $customer = $this->customer();
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/waitlist', [
            'staff_id' => $staff->id,
            'service_id' => $service->id,
            'date' => '2026-09-17',
            'time' => '15:00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('entry.status', 'waiting')
            ->assertJsonPath('entry.position', 1);

        $this->deleteJson('/api/waitlist/'.$response->json('entry.id'))
            ->assertOk()
            ->assertJsonPath('status', true);
        $this->assertDatabaseHas('booking_waitlist_entries', ['user_id' => $customer->id, 'status' => 'cancelled']);
    }

    public function test_available_slots_cannot_be_joined_and_waiter_is_promoted_after_cancellation(): void
    {
        Notification::fake();
        [$staff, $service] = $this->setupSlot();
        $original = $this->occupiedBooking($staff, $service, $this->customer());
        $waitingUser = $this->customer();
        Sanctum::actingAs($waitingUser);

        $join = [
            'staff_id' => $staff->id,
            'service_id' => $service->id,
            'date' => '2026-09-17',
            'time' => '15:00',
        ];
        $this->postJson('/api/waitlist', $join)->assertCreated();

        $this->postJson('/api/waitlist', [
            ...$join,
            'time' => '16:00',
        ])->assertStatus(409);

        $original->update(['status' => 'cancelled']);
        app(BookingWaitlistService::class)->promoteFor($original->fresh());

        $entry = BookingWaitlistEntry::where('user_id', $waitingUser->id)->firstOrFail();
        $this->assertSame('assigned', $entry->status);
        $this->assertNotNull($entry->assigned_booking_id);
        $this->assertDatabaseHas('bookings', [
            'id' => $entry->assigned_booking_id,
            'user_id' => $waitingUser->id,
            'status' => 'confirmed',
        ]);
        Notification::assertSentTo($waitingUser, WaitlistBookingAssignedNotification::class);
    }

    public function test_waitlist_endpoints_require_authentication(): void
    {
        $this->getJson('/api/waitlist')->assertUnauthorized();
        $this->postJson('/api/waitlist', [])->assertUnauthorized();
    }
}
