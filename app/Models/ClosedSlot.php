<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClosedSlot extends Model
{
    protected $fillable = [
        'staff_id',
        'is_global',
        'date',
        'end_date',
        'time',
        'reason',
    ];

    protected $casts = [
        'is_global' => 'boolean',
        'date' => 'date',
        'end_date' => 'date',
        'time' => 'string',
    ];

    /**
     * Relazione: Una closed slot appartiene a uno staff
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function scopeAppliesToStaff($query, int $staffId)
    {
        return $query->where(function ($query) use ($staffId) {
            $query->where('is_global', true)
                ->orWhere('staff_id', $staffId);
        });
    }

    public function scopeCoversDate($query, string $date)
    {
        return $query
            ->whereDate('date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $date);
            });
    }
}
