<?php

namespace Database\Seeders;

use App\Models\Availability;
use Illuminate\Database\Seeder;

class AvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        Availability::query()->update(['is_active' => false]);

        $availabilities = [
            ['weekday' => 2, 'slot_type' => 'morning', 'start_time' => '08:30', 'end_time' => '12:30'],
            ['weekday' => 2, 'slot_type' => 'afternoon', 'start_time' => '15:00', 'end_time' => '20:00'],
            ['weekday' => 3, 'slot_type' => 'morning', 'start_time' => '08:30', 'end_time' => '12:30'],
            ['weekday' => 3, 'slot_type' => 'afternoon', 'start_time' => '15:00', 'end_time' => '20:00'],
            ['weekday' => 4, 'slot_type' => 'morning', 'start_time' => '08:30', 'end_time' => '12:30'],
            ['weekday' => 4, 'slot_type' => 'afternoon', 'start_time' => '15:00', 'end_time' => '20:00'],
            ['weekday' => 5, 'slot_type' => 'morning', 'start_time' => '08:30', 'end_time' => '13:00'],
            ['weekday' => 5, 'slot_type' => 'afternoon', 'start_time' => '14:30', 'end_time' => '21:30'],
            ['weekday' => 6, 'slot_type' => 'morning', 'start_time' => '08:30', 'end_time' => '13:00'],
            ['weekday' => 6, 'slot_type' => 'afternoon', 'start_time' => '13:00', 'end_time' => '21:00'],
        ];

        foreach ($availabilities as $availability) {
            Availability::updateOrCreate(
                [
                    'weekday' => $availability['weekday'],
                    'slot_type' => $availability['slot_type'],
                ],
                $availability + ['is_active' => true]
            );
        }
    }
}
