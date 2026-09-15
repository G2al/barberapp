<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name' => 'Permanente', 'price' => 35, 'duration' => 60],
            ['name' => 'Mash con cuffia', 'price' => 20, 'duration' => 25],
            ['name' => 'Mash con cartina', 'price' => 30, 'duration' => 40],
            ['name' => 'Colore', 'price' => 20, 'duration' => 30],
            ['name' => 'Tintura barba', 'price' => 10, 'duration' => 15],
            ['name' => 'Modellatura barba', 'price' => 5, 'duration' => 15],
            ['name' => 'Taglio bambino', 'price' => 8, 'duration' => 15],
            ['name' => 'Taglio + shampoo bambino', 'price' => 12, 'duration' => 20],
            ['name' => 'Taglio', 'price' => 10, 'duration' => 20],
            ['name' => 'Taglio + shampoo', 'price' => 13, 'duration' => 30],
            ['name' => 'Taglio + shampoo + barba', 'price' => 18, 'duration' => 40],
            ['name' => 'Taglio + shampoo + barba + sopracciglia', 'price' => 20, 'duration' => 45],
            ['name' => 'Sopracciglia', 'price' => 2, 'duration' => 5],
            ['name' => 'Black mask', 'price' => 8, 'duration' => 25],
            ['name' => 'Trattamento barba relax', 'price' => 10, 'duration' => 20],
            ['name' => 'Decolorazione white', 'price' => 80, 'duration' => 120],
            ['name' => 'Barba classica con rasoio monouso', 'price' => 7, 'duration' => 15],
        ];

        foreach ($services as &$service) {
            $service['description'] = 'Listino Mottola Style';
        }
        unset($service);

        $serviceNames = array_column($services, 'name');

        Service::whereNotIn('name', $serviceNames)->update(['is_active' => false]);

        foreach ($services as $service) {
            Service::updateOrCreate(
                ['name' => $service['name']],
                $service + ['is_active' => true]
            );
        }
    }
}
