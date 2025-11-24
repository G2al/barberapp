<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            // --- LISTA PREZZI UFFICIALE ---
            ['name' => 'Shampoo + Piega', 'price' => 7, 'duration' => 15],
            ['name' => 'Shampoo + Taglio Uomo', 'price' => 15, 'duration' => 45],
            ['name' => 'Shampoo + Taglio Bambino', 'price' => 12, 'duration' => 45],
            ['name' => 'Shampoo + Taglio + Barba', 'price' => 20, 'duration' => 45],

            ['name' => 'Barba classica', 'price' => 5, 'duration' => 20],
            ['name' => 'Barba con punto luce', 'price' => 7, 'duration' => 30],
            ['name' => 'Barba + Colore', 'price' => 10, 'duration' => 30],

            ['name' => 'Colore', 'price' => 20, 'duration' => 30],
            ['name' => 'Colpi di Sole / White (piccolo)', 'price' => 30, 'duration' => 45],
            ['name' => 'Colpi di Sole / White (grande)', 'price' => 55, 'duration' => 60],
            ['name' => 'Total White', 'price' => 70, 'duration' => 60],
            ['name' => 'Permanente', 'price' => 40, 'duration' => 45],
            ['name' => 'Stiratura', 'price' => 30, 'duration' => 45],

            ['name' => 'Sopracciglia', 'price' => 3, 'duration' => 10],
            ['name' => 'Black Mask', 'price' => 5, 'duration' => 10],
        ];

        foreach ($services as $service) {
            Service::create($service);
        }
    }
}
