<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_cannot_book_a_service_restricted_for_a_staff_member(): void
    {
        $user = User::factory()->create([
            'surname' => 'Test',
            'phone' => '390000000001',
        ]);
        $service = Service::create([
            'name' => 'Barba con punto luce',
            'price' => 10,
            'duration' => 30,
            'is_active' => true,
        ]);
        $staff = Staff::create([
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'is_active' => true,
        ]);

        $staff->services()->attach($service);
        $user->serviceRestrictions()->create([
            'service_id' => $service->id,
            'staff_id' => $staff->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/bookings', [
            'staff_id' => $staff->id,
            'service_id' => $service->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '10:00',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_a_customer_can_use_the_service_with_another_staff_member(): void
    {
        $user = User::factory()->create([
            'surname' => 'Test',
            'phone' => '390000000002',
        ]);
        $service = Service::create([
            'name' => 'Barba con punto luce',
            'price' => 10,
            'duration' => 30,
            'is_active' => true,
        ]);
        $blockedStaff = Staff::create([
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'is_active' => true,
        ]);
        $availableStaff = Staff::create([
            'first_name' => 'Luca',
            'last_name' => 'Bianchi',
            'is_active' => true,
        ]);

        $blockedStaff->services()->attach($service);
        $availableStaff->services()->attach($service);
        $user->serviceRestrictions()->create([
            'service_id' => $service->id,
            'staff_id' => $blockedStaff->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/staff/by-service/' . $service->id);

        $response->assertOk()
            ->assertJsonMissing(['id' => $blockedStaff->id])
            ->assertJsonFragment(['id' => $availableStaff->id]);
    }
}
