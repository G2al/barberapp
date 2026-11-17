<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        // Prezzi dal listino Ciro Expo ("da" = prezzo minimo).
        // Taglio + shampoo / + barba non sono a listino: somma dei singoli (da confermare).
        $services = [
            ['name' => 'Barba', 'price' => 5, 'duration' => 30],
            ['name' => 'Shampoo + styling', 'price' => 5, 'duration' => 20],
            ['name' => 'Barba color', 'price' => 7, 'duration' => 40],
            ['name' => 'Hair color', 'price' => 10, 'duration' => 30],
            ['name' => 'Decolorazione', 'price' => 50, 'duration' => 70],
            ['name' => 'Sistema ondulante', 'price' => 35, 'duration' => 30],
            ['name' => 'Barba relax', 'price' => 10, 'duration' => 20],
            ['name' => 'Trattamento antiforfora', 'price' => 3, 'duration' => 10],
            ['name' => 'Maschera specifica', 'price' => 3, 'duration' => 15],
            ['name' => 'Taglio + shampoo', 'price' => 18, 'duration' => 50],
            ['name' => 'Taglio + shampoo + barba', 'price' => 23, 'duration' => 60],
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
