<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'admin@barberapp.com',
            ],
            [
                'name'      => 'Admin',
                'surname'   => 'Master',
                'phone'     => '9999999999',
                'password'  => 'password',   // Verrà criptata automaticamente
                'role'      => 'admin',
                'is_active' => true,
            ]
        );
    }
}
