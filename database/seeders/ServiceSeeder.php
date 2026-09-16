<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name' => 'Barba', 'price' => 5, 'duration' => 15],
            ['name' => 'Shampoo acconciatura', 'price' => 5, 'duration' => 10],
            ['name' => 'Pulizia del viso', 'price' => 30, 'duration' => 45],
            ['name' => 'Decolorazione', 'price' => 70, 'duration' => 120],
            ['name' => 'Tintura barba', 'price' => 5, 'duration' => 30],
            ['name' => 'Taglio e Shampoo', 'price' => 12, 'duration' => 30],
            ['name' => 'Taglio - Shampoo - barba', 'price' => 17, 'duration' => 45],
            ['name' => 'Sopracciglia', 'price' => 3, 'duration' => 5],
            ['name' => 'Meches - taglio', 'price' => 50, 'duration' => 165],
            ['name' => 'Trattamento Keratina', 'price' => 50, 'duration' => 135],
            ['name' => 'Taglio bambino', 'price' => 10, 'duration' => 30],
        ];

        $serviceNames = array_column($services, 'name');

        Service::whereNotIn('name', $serviceNames)->update(['is_active' => false]);

        foreach ($services as $service) {
            Service::updateOrCreate(
                ['name' => $service['name']],
                $service + ['description' => null, 'is_active' => true]
            );
        }
    }
}
