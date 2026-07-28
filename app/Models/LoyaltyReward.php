<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoyaltyReward extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_REDEEMED = 'redeemed';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'loyalty_reward_rule_id',
        'reward_service_id',
        'title',
        'description',
        'points_cost',
        'status',
        'code',
        'earned_at',
        'redeemed_at',
        'expires_at',
    ];

    protected $casts = [
        'points_cost' => 'integer',
        'earned_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rule()
    {
        return $this->belongsTo(LoyaltyRewardRule::class, 'loyalty_reward_rule_id');
    }

    public function rewardService()
    {
        return $this->belongsTo(Service::class, 'reward_service_id');
    }

    public function transactions()
    {
        return $this->hasMany(LoyaltyPointTransaction::class);
    }
}
