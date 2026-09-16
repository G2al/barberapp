<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingWaitlistEntry extends Model
{
    protected $fillable = [
        'user_id', 'staff_id', 'service_id', 'date', 'time', 'status', 'assigned_booking_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function assignedBooking()
    {
        return $this->belongsTo(Booking::class, 'assigned_booking_id');
    }
}
