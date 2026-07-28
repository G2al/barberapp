<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class LoyaltyServicePointSeeder extends Seeder
{
    public function run(): void
    {
        $pointsByService = [
            'Taglio + Shampoo' => 15,
            'Taglio + Shampoo Under 12' => 12,
            'Barba tradizionale' => 10,
            'Modellatura barba' => 10,
            'Sopracciglia' => 3,
            'Trattamento viso' => 5,
            'Trattamento barba' => 5,
            'Trattamento a vapore' => 5,
            'Taglio + Shampoo Experience' => 25,
            'Barba Experience' => 15,
            'Barba Speakeasy' => 18,
            'Barba Sensorial' => 20,
            'Trattamento Experience' => 15,
            'Pulizia del viso' => 25,
        ];

        foreach ($pointsByService as $name => $points) {
            Service::where('name', $name)->update(['loyalty_points' => $points]);
        }
    }
}
