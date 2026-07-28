<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\LoyaltyPointTransaction;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyRewardRule;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoyaltyProgramTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_booking_awards_points_and_creates_reward_once(): void
    {
        $user = User::factory()->create([
            'surname' => 'Cliente',
            'phone' => '3331112223',
        ]);
        $staff = Staff::create([
            'first_name' => 'Giovanni',
            'last_name' => 'Cerino',
            'is_active' => true,
        ]);
        $service = Service::create([
            'name' => 'Barba tradizionale',
            'price' => 5,
            'duration' => 15,
            'loyalty_points' => 100,
            'is_active' => true,
        ]);

        LoyaltyRewardRule::create([
            'name' => '100 punti - Barba omaggio',
            'type' => LoyaltyRewardRule::TYPE_POINTS_THRESHOLD,
            'points_required' => 100,
            'reward_service_id' => $service->id,
            'reward_points_cost' => 100,
            'reward_title' => 'Barba omaggio',
            'is_repeatable' => true,
            'is_active' => true,
        ]);

        $booking = Booking::create([
            'user_id' => $user->id,
            'staff_id' => $staff->id,
            'service_id' => $service->id,
            'date' => now()->toDateString(),
            'time' => '10:00',
            'status' => 'confirmed',
        ]);

        $booking->update(['status' => 'completed']);
        $booking->update(['status' => 'confirmed']);
        $booking->update(['status' => 'completed']);

        $this->assertSame(1, LoyaltyPointTransaction::where('booking_id', $booking->id)->count());
        $this->assertSame(100, (int) $user->loyaltyPointTransactions()->sum('points'));
        $this->assertSame(1, LoyaltyReward::where('user_id', $user->id)->count());
    }

    public function test_user_can_read_and_redeem_available_reward(): void
    {
        $user = User::factory()->create([
            'surname' => 'Cliente',
            'phone' => '3331112224',
        ]);
        Sanctum::actingAs($user);

        $rule = LoyaltyRewardRule::create([
            'name' => 'Premio test',
            'type' => LoyaltyRewardRule::TYPE_POINTS_THRESHOLD,
            'points_required' => 100,
            'reward_points_cost' => 0,
            'reward_title' => 'Premio test',
            'is_active' => true,
        ]);

        $reward = LoyaltyReward::create([
            'user_id' => $user->id,
            'loyalty_reward_rule_id' => $rule->id,
            'title' => 'Premio test',
            'points_cost' => 0,
            'status' => LoyaltyReward::STATUS_AVAILABLE,
            'code' => 'TESTCODE',
            'earned_at' => now(),
        ]);

        $this->getJson('/api/loyalty/summary')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('loyalty.available_rewards_count', 1);

        $this->postJson("/api/loyalty/rewards/{$reward->id}/redeem")
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('reward.status', LoyaltyReward::STATUS_REDEEMED);
    }
}
