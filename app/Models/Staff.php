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
        'department',
        'phone',
        'image',
        'is_active',
    ];

    public function services()
    {
        return $this->belongsToMany(Service::class, 'staff_service');
    }
    
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function closedSlots()
    {
        return $this->hasMany(ClosedSlot::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getDepartmentLabelAttribute(): string
    {
        return match ($this->department) {
            'beauty' => 'Estetica',
            default => 'Parrucchiera',
        };
    }
}
