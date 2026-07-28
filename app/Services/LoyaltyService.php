<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\LoyaltyPointTransaction;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyRewardRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoyaltyService
{
    public function awardCompletedBooking(Booking $booking): void
    {
        if ($booking->status !== 'completed') {
            return;
        }

        $booking->loadMissing(['service', 'user']);

        if (!$booking->user || !$booking->service) {
            return;
        }

        $points = (int) ($booking->service->loyalty_points ?? 0);

        DB::transaction(function () use ($booking, $points): void {
            if ($points > 0) {
                LoyaltyPointTransaction::firstOrCreate(
                    [
                        'booking_id' => $booking->id,
                        'type' => LoyaltyPointTransaction::TYPE_EARNED,
                    ],
                    [
                        'user_id' => $booking->user_id,
                        'service_id' => $booking->service_id,
                        'points' => $points,
                        'description' => 'Punti per ' . $booking->service->name,
                        'metadata' => [
                            'booking_date' => optional($booking->date)->format('Y-m-d'),
                            'booking_time' => $booking->time,
                        ],
                    ]
                );
            }

            $this->issueEarnedRewards($booking->user);
        });
    }

    public function summaryFor(User $user): array
    {
        $balance = $this->pointsBalance($user);
        $lifetimePoints = $this->lifetimeEarnedPoints($user);

        $nextRule = LoyaltyRewardRule::query()
            ->where('is_active', true)
            ->where('type', LoyaltyRewardRule::TYPE_POINTS_THRESHOLD)
            ->whereNotNull('points_required')
            ->where('points_required', '>', $lifetimePoints)
            ->orderBy('points_required')
            ->first();

        $availableRewards = $user->loyaltyRewards()
            ->with(['rewardService', 'rule.service'])
            ->where('status', LoyaltyReward::STATUS_AVAILABLE)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->latest('earned_at')
            ->get();

        return [
            'balance' => $balance,
            'lifetime_points' => $lifetimePoints,
            'available_rewards_count' => $availableRewards->count(),
            'next_reward' => $nextRule ? [
                'name' => $nextRule->reward_title,
                'points_required' => $nextRule->points_required,
                'points_missing' => max(0, $nextRule->points_required - $lifetimePoints),
                'progress' => $nextRule->points_required > 0
                    ? min(100, (int) round(($lifetimePoints / $nextRule->points_required) * 100))
                    : 100,
            ] : null,
            'rewards' => $availableRewards->map(fn (LoyaltyReward $reward): array => $this->formatReward($reward))->values(),
            'transactions' => $user->loyaltyPointTransactions()
                ->with(['service', 'reward'])
                ->latest()
                ->limit(12)
                ->get()
                ->map(fn (LoyaltyPointTransaction $transaction): array => [
                    'id' => $transaction->id,
                    'points' => $transaction->points,
                    'type' => $transaction->type,
                    'description' => $transaction->description,
                    'service' => $transaction->service?->name,
                    'created_at' => $transaction->created_at?->toIso8601String(),
                ])
                ->values(),
            'rules' => LoyaltyRewardRule::query()
                ->with(['service', 'rewardService'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('points_required')
                ->get()
                ->map(fn (LoyaltyRewardRule $rule): array => $this->formatRule($rule, $user))
                ->values(),
        ];
    }

    public function redeem(LoyaltyReward $reward): LoyaltyReward
    {
        if ($reward->status !== LoyaltyReward::STATUS_AVAILABLE) {
            throw new \RuntimeException('Questo premio non e\' disponibile.');
        }

        if ($reward->expires_at && $reward->expires_at->isPast()) {
            $reward->update(['status' => LoyaltyReward::STATUS_EXPIRED]);
            throw new \RuntimeException('Questo premio e\' scaduto.');
        }

        return DB::transaction(function () use ($reward): LoyaltyReward {
            $reward->update([
                'status' => LoyaltyReward::STATUS_REDEEMED,
                'redeemed_at' => now(),
            ]);

            if ($reward->points_cost > 0) {
                LoyaltyPointTransaction::create([
                    'user_id' => $reward->user_id,
                    'loyalty_reward_id' => $reward->id,
                    'points' => -1 * $reward->points_cost,
                    'type' => LoyaltyPointTransaction::TYPE_REDEEMED,
                    'description' => 'Premio usato: ' . $reward->title,
                ]);
            }

            return $reward->refresh();
        });
    }

    private function issueEarnedRewards(User $user): void
    {
        LoyaltyRewardRule::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->each(function (LoyaltyRewardRule $rule) use ($user): void {
                $earnedCount = $this->earnedRewardCountForRule($rule, $user);

                if ($earnedCount <= 0) {
                    return;
                }

                $existingCount = $user->loyaltyRewards()
                    ->where('loyalty_reward_rule_id', $rule->id)
                    ->count();

                if (!$rule->is_repeatable && $existingCount > 0) {
                    return;
                }

                $toCreate = $rule->is_repeatable
                    ? max(0, $earnedCount - $existingCount)
                    : 1;

                for ($i = 0; $i < $toCreate; $i++) {
                    $this->createRewardFromRule($rule, $user);
                }
            });
    }

    private function earnedRewardCountForRule(LoyaltyRewardRule $rule, User $user): int
    {
        if ($rule->type === LoyaltyRewardRule::TYPE_POINTS_THRESHOLD && $rule->points_required) {
            return intdiv($this->lifetimeEarnedPoints($user), $rule->points_required);
        }

        if ($rule->type === LoyaltyRewardRule::TYPE_SERVICE_COUNT && $rule->service_id && $rule->visits_required) {
            $completedVisits = Booking::query()
                ->where('user_id', $user->id)
                ->where('service_id', $rule->service_id)
                ->where('status', 'completed')
                ->count();

            return intdiv($completedVisits, $rule->visits_required);
        }

        return 0;
    }

    private function createRewardFromRule(LoyaltyRewardRule $rule, User $user): LoyaltyReward
    {
        return LoyaltyReward::create([
            'user_id' => $user->id,
            'loyalty_reward_rule_id' => $rule->id,
            'reward_service_id' => $rule->reward_service_id,
            'title' => $rule->reward_title,
            'description' => $rule->reward_description,
            'points_cost' => $rule->reward_points_cost,
            'status' => LoyaltyReward::STATUS_AVAILABLE,
            'code' => $this->generateRewardCode(),
            'earned_at' => now(),
            'expires_at' => $rule->expires_after_days ? now()->addDays($rule->expires_after_days) : null,
        ]);
    }

    private function pointsBalance(User $user): int
    {
        return (int) $user->loyaltyPointTransactions()->sum('points');
    }

    private function lifetimeEarnedPoints(User $user): int
    {
        return (int) $user->loyaltyPointTransactions()
            ->where('type', LoyaltyPointTransaction::TYPE_EARNED)
            ->sum('points');
    }

    private function formatReward(LoyaltyReward $reward): array
    {
        return [
            'id' => $reward->id,
            'title' => $reward->title,
            'description' => $reward->description,
            'points_cost' => $reward->points_cost,
            'status' => $reward->status,
            'code' => $reward->code,
            'service' => $reward->rewardService?->name,
            'earned_at' => $reward->earned_at?->toIso8601String(),
            'expires_at' => $reward->expires_at?->toIso8601String(),
        ];
    }

    private function formatRule(LoyaltyRewardRule $rule, User $user): array
    {
        $current = $rule->type === LoyaltyRewardRule::TYPE_SERVICE_COUNT
            ? Booking::query()
                ->where('user_id', $user->id)
                ->where('service_id', $rule->service_id)
                ->where('status', 'completed')
                ->count()
            : $this->lifetimeEarnedPoints($user);

        $target = $rule->type === LoyaltyRewardRule::TYPE_SERVICE_COUNT
            ? (int) $rule->visits_required
            : (int) $rule->points_required;

        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'type' => $rule->type,
            'service' => $rule->service?->name,
            'reward_title' => $rule->reward_title,
            'reward_description' => $rule->reward_description,
            'current' => $target > 0 ? $current % $target : $current,
            'target' => $target,
            'progress' => $target > 0 ? min(100, (int) round((($current % $target) / $target) * 100)) : 100,
        ];
    }

    private function generateRewardCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (LoyaltyReward::where('code', $code)->exists());

        return $code;
    }
}
