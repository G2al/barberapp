<?php

namespace App\Services\Ai;

use App\Models\Setting;

class PublicBookingRules
{
    public function toArray(): array
    {
        $maxActiveBookings = (int) Setting::get('max_active_bookings', 3);

        return [
            'max_active_bookings' => $maxActiveBookings <= 0 ? null : $maxActiveBookings,
            'booking' => [
                'dates' => 'Si puo prenotare da oggi in avanti.',
                'availability' => 'Sono selezionabili soltanto gli slot restituiti come disponibili dall app.',
                'duration' => 'Ogni servizio occupa lo staff per la durata configurata nel servizio.',
            ],
            'cancellation' => [
                'allowed_statuses' => ['pending', 'confirmed'],
                'rule' => 'Una prenotazione attiva puo essere annullata prima dell orario di inizio.',
            ],
            'schedule_priority' => [
                'aperture straordinarie della data',
                'orari specifici dello staff',
                'orari ordinari del salone',
            ],
        ];
    }
}
