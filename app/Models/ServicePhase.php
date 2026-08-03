<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServicePhase extends Model
{
    protected $fillable = [
        'name',
        'duration',
        'staff_required',
        'position',
    ];

    protected $casts = [
        'duration' => 'integer',
        'staff_required' => 'boolean',
        'position' => 'integer',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
