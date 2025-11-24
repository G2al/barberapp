<?php

namespace Database\Seeders;

use App\Models\Availability;
use Illuminate\Database\Seeder;

class AvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        // Martedì (1) - Mattina
        Availability::create([
            'weekday' => 2,
            'slot_type' => 'morning',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'is_active' => true,
        ]);

        // Martedì (2) - Pomeriggio
        Availability::create([
            'weekday' => 2,
            'slot_type' => 'afternoon',
            'start_time' => '15:00',
            'end_time' => '19:00',
            'is_active' => true,
        ]);

        // Mercoledì (3) - Mattina
        Availability::create([
            'weekday' => 3,
            'slot_type' => 'morning',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'is_active' => true,
        ]);

        // Mercoledì (3) - Pomeriggio
        Availability::create([
            'weekday' => 3,
            'slot_type' => 'afternoon',
            'start_time' => '15:00',
            'end_time' => '19:00',
            'is_active' => true,
        ]);

        // Giovedì (4) - Mattina
        Availability::create([
            'weekday' => 4,
            'slot_type' => 'morning',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'is_active' => true,
        ]);

        // Giovedì (4) - Pomeriggio
        Availability::create([
            'weekday' => 4,
            'slot_type' => 'afternoon',
            'start_time' => '15:00',
            'end_time' => '19:00',
            'is_active' => true,
        ]);

        // Venerdì (5) - Mattina
        Availability::create([
            'weekday' => 5,
            'slot_type' => 'morning',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'is_active' => true,
        ]);

        // Venerdì (5) - Pomeriggio
        Availability::create([
            'weekday' => 5,
            'slot_type' => 'afternoon',
            'start_time' => '15:00',
            'end_time' => '19:00',
            'is_active' => true,
        ]);

        // Sabato (6) - Orario continuo
        Availability::create([
            'weekday' => 6,
            'slot_type' => 'morning',
            'start_time' => '09:00',
            'end_time' => '19:00',
            'is_active' => true,
        ]);

        // Domenica (0) e Lunedì (1) = CHIUSI (non aggiungiamo nulla)
    }
}