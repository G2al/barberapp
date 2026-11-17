<?php

namespace Tests\Feature;

use App\Models\Availability;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CiroExpoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_loads_only_ciro_expo_business_data_and_is_repeatable(): void
    {
        $oldService = Service::create([
            'name' => 'Servizio non incluso nel seed',
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

        $admin = User::where('email', 'admin@ciroexpoapp.it')->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('password', $admin->password));

        $staff = Staff::where('first_name', 'Mario')->where('last_name', 'Mango')->firstOrFail();
        $this->assertTrue($staff->is_active);
        $this->assertTrue($staff->uses_salon_hours);
        $this->assertSame('+393317189966', $staff->phone);
        $this->assertFalse($oldStaff->fresh()->is_active);
        $this->assertFalse((bool) $oldService->fresh()->is_active);
        $this->assertSame(11, Service::where('is_active', true)->count());
        $this->assertSame(11, $staff->services()->count());

        $this->assertDatabaseHas('services', [
            'name' => 'Decolorazione',
            'price' => 50,
            'duration' => 70,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('services', [
            'name' => 'Taglio + shampoo + barba',
            'price' => 23,
            'duration' => 60,
            'is_active' => true,
        ]);

        $this->assertCount(10, Availability::where('is_active', true)->get());
        $this->assertDatabaseHas('availabilities', [
            'weekday' => 6,
            'slot_type' => 'afternoon',
            'start_time' => '14:00',
            'end_time' => '19:00',
            'is_active' => true,
        ]);
        $this->assertSame(0, Availability::whereIn('weekday', [0, 1])->where('is_active', true)->count());
        $this->assertSame(1, Staff::where('first_name', 'Mario')->where('last_name', 'Mango')->count());
    }
}
