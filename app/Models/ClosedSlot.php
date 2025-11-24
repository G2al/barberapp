<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClosedSlot extends Model
{
    protected $fillable = [
        'staff_id',
        'date',
        'time',
        'reason',
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'string',
    ];

    /**
     * Relazione: Una closed slot appartiene a uno staff
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
