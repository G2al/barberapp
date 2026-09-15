<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Staff extends Model
{
    protected $table = 'staff';

    protected $fillable = [
        'first_name',
        'last_name',
        'role',
        'phone',
        'image',
        'is_active',
        'uses_salon_hours',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'uses_salon_hours' => 'boolean',
    ];

    public function services()
    {
        return $this->belongsToMany(Service::class, 'staff_service');
    }
    
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function availabilities()
    {
        return $this->hasMany(StaffAvailability::class);
    }

    public function closedSlots()
    {
        return $this->hasMany(ClosedSlot::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
