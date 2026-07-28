<?php

namespace Database\Seeders;

use App\Models\LoyaltyRewardRule;
use App\Models\Service;
use Illuminate\Database\Seeder;

class LoyaltyRewardRuleSeeder extends Seeder
{
    public function run(): void
    {
        $barba = Service::where('name', 'Barba tradizionale')->first();
        $taglio = Service::where('name', 'Taglio + Shampoo')->first();

        LoyaltyRewardRule::updateOrCreate(
            ['name' => '100 punti - Barba omaggio'],
            [
                'type' => LoyaltyRewardRule::TYPE_POINTS_THRESHOLD,
                'points_required' => 100,
                'visits_required' => null,
                'service_id' => null,
                'reward_service_id' => $barba?->id,
                'reward_points_cost' => 100,
                'reward_title' => 'Barba omaggio',
                'reward_description' => 'Hai raggiunto 100 punti: puoi usare una barba tradizionale in omaggio.',
                'expires_after_days' => 90,
                'is_repeatable' => true,
                'is_active' => true,
                'sort_order' => 10,
            ]
        );

        LoyaltyRewardRule::updateOrCreate(
            ['name' => '10 barbe - Barba omaggio'],
            [
                'type' => LoyaltyRewardRule::TYPE_SERVICE_COUNT,
                'points_required' => null,
                'visits_required' => 10,
                'service_id' => $barba?->id,
                'reward_service_id' => $barba?->id,
                'reward_points_cost' => 0,
                'reward_title' => 'La prossima barba la offriamo noi',
                'reward_description' => 'Dopo 10 barbe completate, una barba tradizionale e\' in omaggio.',
                'expires_after_days' => 120,
                'is_repeatable' => true,
                'is_active' => true,
                'sort_order' => 20,
            ]
        );

        LoyaltyRewardRule::updateOrCreate(
            ['name' => '200 punti - Taglio premium'],
            [
                'type' => LoyaltyRewardRule::TYPE_POINTS_THRESHOLD,
                'points_required' => 200,
                'visits_required' => null,
                'service_id' => null,
                'reward_service_id' => $taglio?->id,
                'reward_points_cost' => 200,
                'reward_title' => 'Taglio + Shampoo omaggio',
                'reward_description' => 'Premio premium per i clienti piu\' fedeli.',
                'expires_after_days' => 120,
                'is_repeatable' => true,
                'is_active' => true,
                'sort_order' => 30,
            ]
        );
    }
}
