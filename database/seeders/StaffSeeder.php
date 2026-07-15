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
                'first_name' => 'Giovanni',
                'last_name' => 'Cerino',
                'role' => 'barber',
                'phone' => '+39 324 099 4144',
                'is_active' => true,
            ],
            [
                'first_name' => 'Antonio',
                'last_name' => 'Pecorario',
                'role' => 'barber',
                'phone' => '+39 324 563 1120',
                'is_active' => true,
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

            $staff->services()->sync($serviceIds);
        }
    }
}
