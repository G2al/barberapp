<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoyaltyPointTransaction extends Model
{
    use HasFactory;

    public const TYPE_EARNED = 'earned';
    public const TYPE_REDEEMED = 'redeemed';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'user_id',
        'booking_id',
        'service_id',
        'loyalty_reward_id',
        'points',
        'type',
        'description',
        'metadata',
    ];

    protected $casts = [
        'points' => 'integer',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function reward()
    {
        return $this->belongsTo(LoyaltyReward::class, 'loyalty_reward_id');
    }
}
