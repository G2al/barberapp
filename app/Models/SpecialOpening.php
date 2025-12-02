<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpecialOpening extends Model
{
    protected $fillable = [
        'date',
        'start_time',
        'end_time',
        'is_active',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'is_active' => 'boolean',
    ];
}
