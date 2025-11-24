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
        'price',
        'duration',
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
}
