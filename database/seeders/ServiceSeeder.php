<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name' => 'Taglio + Shampoo', 'price' => 15, 'duration' => 30, 'description' => 'Listino Giovanni Cerino Hair Stylist'],
            ['name' => 'Taglio + Shampoo Under 12', 'price' => 12, 'duration' => 30, 'description' => 'Listino Giovanni Cerino Hair Stylist'],
            ['name' => 'Barba tradizionale', 'price' => 5, 'duration' => 15, 'description' => 'Listino Giovanni Cerino Hair Stylist'],
            ['name' => 'Modellatura barba', 'price' => 5, 'duration' => 15, 'description' => 'Listino Giovanni Cerino Hair Stylist'],
            ['name' => 'Sopracciglia', 'price' => 3, 'duration' => 10, 'description' => 'Listino Giovanni Cerino Hair Stylist'],
            ['name' => 'Trattamento viso', 'price' => 5, 'duration' => 15, 'description' => 'Listino Giovanni Cerino Hair Stylist'],
            ['name' => 'Trattamento barba', 'price' => 5, 'duration' => 15, 'description' => 'Listino Giovanni Cerino Hair Stylist'],
            ['name' => 'Trattamento a vapore', 'price' => 5, 'duration' => 15, 'description' => 'Listino Giovanni Cerino Hair Stylist'],
            ['name' => 'Taglio + Shampoo Experience', 'price' => 20, 'duration' => 45, 'description' => 'Experience Room'],
            ['name' => 'Barba Experience', 'price' => 10, 'duration' => 30, 'description' => 'Experience Room'],
            ['name' => 'Barba Speakeasy', 'price' => 13, 'duration' => 30, 'description' => 'Experience Room'],
            ['name' => 'Barba Sensorial', 'price' => 15, 'duration' => 30, 'description' => 'Experience Room'],
            ['name' => 'Trattamento Experience', 'price' => 10, 'duration' => 30, 'description' => 'Experience Room'],
            ['name' => 'Pulizia del viso', 'price' => 20, 'duration' => 45, 'description' => 'Experience Room'],
        ];

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
