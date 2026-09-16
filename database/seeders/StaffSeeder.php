<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Staff;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $staffMembers = [
            [
                'first_name' => 'Gabriele',
                'last_name' => 'Del Piano',
                'role' => 'barber',
                'is_active' => true,
                'uses_salon_hours' => true,
            ],
        ];

        $serviceIds = Service::where('is_active', true)->pluck('id')->all();

        foreach ($staffMembers as $staffData) {
            $staff = Staff::updateOrCreate(
                [
                    'first_name' => $staffData['first_name'],
                    'last_name' => $staffData['last_name'],
                ],
                $staffData
            );

            Staff::whereKeyNot($staff->getKey())->update(['is_active' => false]);
            $staff->services()->sync($serviceIds);
        }
    }
}
