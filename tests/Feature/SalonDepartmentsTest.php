<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalonDepartmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_be_filtered_by_department(): void
    {
        Staff::create([
            'first_name' => 'Flora',
            'last_name' => '',
            'role' => 'Parrucchiera',
            'department' => 'hair',
            'is_active' => true,
        ]);

        Staff::create([
            'first_name' => 'Carmela',
            'last_name' => '',
            'role' => 'Estetista',
            'department' => 'beauty',
            'is_active' => true,
        ]);

        $this->getJson('/api/staff?department=beauty')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.first_name', 'Carmela')
            ->assertJsonPath('0.department', 'beauty');
    }

    public function test_staff_can_be_saved_without_last_name(): void
    {
        $staff = Staff::create([
            'first_name' => 'Flora',
            'last_name' => null,
            'role' => 'Parrucchiera',
            'department' => 'hair',
            'is_active' => true,
        ]);

        $this->assertNull($staff->fresh()->last_name);
        $this->assertSame('Flora', $staff->full_name);
    }

    public function test_services_expose_department_and_price_mode(): void
    {
        Service::create([
            'name' => 'Piega torchon',
            'department' => 'hair',
            'price' => 15,
            'price_type' => 'starting_from',
            'duration' => 45,
            'loyalty_points' => 0,
            'is_active' => true,
        ]);

        Service::create([
            'name' => 'Pedicure',
            'department' => 'beauty',
            'price' => 15,
            'price_type' => 'fixed',
            'duration' => 45,
            'loyalty_points' => 0,
            'is_active' => true,
        ]);

        $this->getJson('/api/services?department=hair')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Piega torchon')
            ->assertJsonPath('0.department', 'hair')
            ->assertJsonPath('0.price_type', 'starting_from');
    }

    public function test_service_formats_starting_price_for_the_admin_panel(): void
    {
        $service = new Service([
            'price' => 15,
            'price_type' => 'starting_from',
        ]);

        $this->assertSame('A partire da EUR 15,00', str_replace("\u{20AC}", 'EUR', $service->formatted_price));
    }
}
