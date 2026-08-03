<?php

namespace Database\Seeders;

use App\Models\Availability;
use Illuminate\Database\Seeder;

class AvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        Availability::query()->update(['is_active' => false]);

        foreach (range(2, 6) as $weekday) {
            Availability::updateOrCreate(
                [
                    'weekday' => $weekday,
                    'slot_type' => 'continuous',
                ],
                [
                    'start_time' => '08:30',
                    'end_time' => '18:00',
                    'is_active' => true,
                ]
            );
        }
    }
}
