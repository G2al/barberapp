<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ClosedSlot extends Model
{
    protected $fillable = [
        'staff_id',
        'is_global',
        'date',
        'end_date',
        'time',
        'start_time',
        'end_time',
        'reason',
    ];

    protected $casts = [
        'is_global' => 'boolean',
        'date' => 'date',
        'end_date' => 'date',
        'time' => 'string',
        'start_time' => 'string',
        'end_time' => 'string',
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

    public function blocksInterval(Carbon $intervalStart, Carbon $intervalEnd): bool
    {
        if ($this->start_time !== null && $this->end_time !== null) {
            $closedStart = Carbon::createFromFormat('H:i', substr($this->start_time, 0, 5));
            $closedEnd = Carbon::createFromFormat('H:i', substr($this->end_time, 0, 5));

            return $intervalStart->lt($closedEnd) && $intervalEnd->gt($closedStart);
        }

        if ($this->time === null) {
            return true;
        }

        return $intervalStart->format('H:i') === substr((string) $this->time, 0, 5);
    }
}
