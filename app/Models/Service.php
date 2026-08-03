<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'department',
        'price',
        'price_type',
        'duration',
        'loyalty_points',
        'is_active',
    ];

    public function staff()
    {
        return $this->belongsToMany(Staff::class, 'staff_service');
    }

    public function bookings()
{
    return $this->hasMany(Booking::class);
}

    public function loyaltyRules()
    {
        return $this->hasMany(LoyaltyRewardRule::class);
    }

    public function phases()
    {
        return $this->hasMany(ServicePhase::class)->orderBy('position');
    }

    public function getDepartmentLabelAttribute(): string
    {
        return match ($this->department) {
            'beauty' => 'Estetica',
            default => 'Parrucchiera',
        };
    }

    public function getFormattedPriceAttribute(): string
    {
        if ($this->price === null) {
            return 'Non disponibile online';
        }

        $price = "\u{20AC} " . number_format((float) $this->price, 2, ',', '.');

        return $this->price_type === 'starting_from'
            ? "A partire da {$price}"
            : $price;
    }
}
