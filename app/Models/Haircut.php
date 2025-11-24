<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Haircut extends Model
{
    protected $fillable = [
        'name',
        'description',
        'photo',
        'is_active',
    ];

    /**
     * Relazione: Un haircut ha molte bookings
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}