<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoyaltyRewardRule extends Model
{
    use HasFactory;

    public const TYPE_POINTS_THRESHOLD = 'points_threshold';
    public const TYPE_SERVICE_COUNT = 'service_count';

    protected $fillable = [
        'name',
        'type',
        'service_id',
        'reward_service_id',
        'points_required',
        'visits_required',
        'reward_points_cost',
        'reward_title',
        'reward_description',
        'expires_after_days',
        'is_repeatable',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'points_required' => 'integer',
        'visits_required' => 'integer',
        'reward_points_cost' => 'integer',
        'expires_after_days' => 'integer',
        'is_repeatable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function rewardService()
    {
        return $this->belongsTo(Service::class, 'reward_service_id');
    }

    public function rewards()
    {
        return $this->hasMany(LoyaltyReward::class);
    }
}
