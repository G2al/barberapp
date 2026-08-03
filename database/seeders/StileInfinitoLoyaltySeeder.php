<?php

namespace Database\Seeders;

use App\Models\LoyaltyRewardRule;
use Illuminate\Database\Seeder;

class StileInfinitoLoyaltySeeder extends Seeder
{
    public function run(): void
    {
        // Le promozioni comunicate richiedono gruppi di servizi e vincoli sul sabato.
        // Restano disattivate finche tali regole non saranno modellate correttamente.
        LoyaltyRewardRule::query()->update(['is_active' => false]);
    }
}
