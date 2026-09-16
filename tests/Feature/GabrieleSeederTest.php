<?php

namespace Tests\Feature;

use App\Models\Availability;
use App\Models\Service;
use App\Models\Staff;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GabrieleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_loads_only_gabriele_business_data_and_is_repeatable(): void
    {
        $oldService = Service::create([
            'name' => 'Vecchio servizio Mottola',
            'price' => 99,
            'duration' => 60,
            'is_active' => true,
        ]);
        $oldStaff = Staff::create([
            'first_name' => 'Vecchio',
            'last_name' => 'Staff',
            'role' => 'barber',
            'is_active' => true,
            'uses_salon_hours' => true,
        ]);

        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--no-interaction' => true]);
        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--no-interaction' => true]);

        $staff = Staff::where('first_name', 'Gabriele')->where('last_name', 'Del Piano')->firstOrFail();
        $this->assertTrue($staff->is_active);
        $this->assertTrue($staff->uses_salon_hours);
        $this->assertFalse($oldStaff->fresh()->is_active);
        $this->assertFalse((bool) $oldService->fresh()->is_active);
        $this->assertSame(11, Service::where('is_active', true)->count());
        $this->assertSame(11, $staff->services()->count());

        $this->assertDatabaseHas('services', [
            'name' => 'Meches - taglio',
            'price' => 50,
            'duration' => 165,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('services', [
            'name' => 'Tintura barba',
            'price' => 5,
            'duration' => 30,
            'is_active' => true,
        ]);

        $activeHours = Availability::where('is_active', true)
            ->orderBy('weekday')
            ->orderBy('slot_type')
            ->get(['weekday', 'slot_type', 'start_time', 'end_time']);

        $this->assertCount(10, $activeHours);
        $this->assertDatabaseHas('availabilities', [
            'weekday' => 5,
            'slot_type' => 'afternoon',
            'start_time' => '14:30',
            'end_time' => '21:30',
            'is_active' => true,
        ]);
        $this->assertSame(0, Availability::whereIn('weekday', [0, 1])->where('is_active', true)->count());
        $this->assertSame(1, Staff::where('first_name', 'Gabriele')->where('last_name', 'Del Piano')->count());
    }
}
