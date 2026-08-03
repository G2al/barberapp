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
                'first_name' => 'Flora',
                'last_name' => null,
                'role' => 'Parrucchiera',
                'department' => 'hair',
                'phone' => '+39 349 652 1221',
                'is_active' => true,
            ],
            [
                'first_name' => 'Carmela',
                'last_name' => null,
                'role' => 'Estetista',
                'department' => 'beauty',
                'phone' => '+39 349 652 1221',
                'is_active' => true,
            ],
        ];

        Staff::query()->update(['is_active' => false]);

        foreach ($staffMembers as $staffData) {
            $staff = Staff::updateOrCreate(
                ['first_name' => $staffData['first_name']],
                $staffData
            );

            $serviceIds = Service::query()
                ->where('is_active', true)
                ->where('department', $staff->department)
                ->pluck('id');

            $staff->services()->sync($serviceIds);
        }
    }
}
