<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffAvailability extends Model
{
    protected $fillable = [
        'staff_id',
        'weekday',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
