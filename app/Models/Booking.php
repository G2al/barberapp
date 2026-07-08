<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'staff_id',
        'service_id',
        'haircut_id',
        'date',
        'time',
        'status',
        'reminder_24h_sent',
        'reminder_3h_sent',
        'reminder_1h_sent',
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'string',
        'reminder_24h_sent' => 'boolean',
        'reminder_3h_sent' => 'boolean',
        'reminder_1h_sent' => 'boolean',
    ];


    /**
     * Relazione: Una booking appartiene a un user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relazione: Una booking appartiene a uno staff
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Relazione: Una booking appartiene a un service
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Relazione: Una booking può appartenere a un haircut (opzionale)
     */
    public function haircut()
    {
        return $this->belongsTo(Haircut::class);
    }

    /**
     * Controlla se lo slot è disponibile (non è chiuso)
     */
    public static function isSlotAvailable($staffId, $date, $time): bool
    {
        return !ClosedSlot::where('staff_id', $staffId)
            ->where('date', $date)
            ->where(function ($query) use ($time) {
                $query->whereNull('time') // Giorno intero chiuso
                    ->orWhere('time', $time); // Orario specifico chiuso
            })
            ->exists();
    }

    /**
     * Conta le prenotazioni attive (confirmed) di un utente con data futura
     */
    public static function countActiveBookings($userId): int
    {
        return static::where('user_id', $userId)
            ->where('status', 'confirmed')
            ->whereDate('date', '>=', \Carbon\Carbon::now()->format('Y-m-d'))
            ->where(function ($query) {
                $query->where(function ($q) {
                    // Data futura
                    $q->whereDate('date', '>', \Carbon\Carbon::now()->format('Y-m-d'));
                })->orWhere(function ($q) {
                    // Stessa data ma orario futuro
                    $q->whereDate('date', '=', \Carbon\Carbon::now()->format('Y-m-d'))
                        ->whereTime('time', '>=', \Carbon\Carbon::now()->format('H:i'));
                });
            })
            ->count();
    }

    /**
     * Controlla se un utente può fare una nuova prenotazione
     */
    public static function canUserBookMore($userId): bool
    {
        $maxBookings = \App\Models\Setting::get('max_active_bookings', 3);
        $activeCount = static::countActiveBookings($userId);
        return $activeCount < (int)$maxBookings;
    }
}
