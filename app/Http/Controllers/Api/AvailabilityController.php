<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Availability;
use App\Models\Booking;
use App\Models\ClosedSlot;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffAvailability;
use App\Models\SpecialOpening;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    /**
     * GET /api/availability/{staffId}?date={date}&serviceId={serviceId}
     * Ritorna gli slot disponibili per uno staff in una data specifica
     * Filtra gli orari passati se la data è oggi
     */
    public function getSlots($staffId, Request $request)
    {
        $date = $request->query('date');
        $serviceId = $request->query('serviceId');

        if (!$date || !$serviceId) {
            return response()->json([
                'status' => false,
                'message' => 'date e serviceId sono obbligatori'
            ], 400);
        }

        $staff = Staff::findOrFail($staffId);
        $service = Service::findOrFail($serviceId);

        if (!$staff->services()->where('service_id', $serviceId)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Questo staff non offre questo servizio'
            ], 400);
        }

        $carbonDate = Carbon::parse($date);
        $weekday = $carbonDate->dayOfWeek;

        // 1) Orari speciali per data (salone)
        $specialOpenings = SpecialOpening::whereDate('date', $date)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        // 2) Orari settimanali per staff specifico
        $staffAvailabilities = StaffAvailability::where('staff_id', $staffId)
            ->where('weekday', $weekday)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        // 3) Orari salone di default
        $salonAvailabilities = Availability::where('weekday', $weekday)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        // Ordine di precedenza: special -> staff -> salone
        if ($specialOpenings->isNotEmpty()) {
            $timeSlots = $specialOpenings;
        } elseif ($staffAvailabilities->isNotEmpty()) {
            $timeSlots = $staffAvailabilities;
        } else {
            $timeSlots = $salonAvailabilities;
        }

        if ($timeSlots->isEmpty()) {
            return response()->json([
                'status' => true,
                'slots' => [],
                'message' => 'Nessun orario disponibile per questo giorno'
            ]);
        }

        $bookings = Booking::where('staff_id', $staffId)
            ->where('date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'completed'])
            ->orderBy('time')
            ->get();

        // ✅ NUOVO: Carica i closed slots per questo staff e data
        $closedSlots = ClosedSlot::where('staff_id', $staffId)
            ->where('date', $date)
            ->get();

        $serviceDuration = $service->duration;
        $allSlots = [];

        // Genera tutti gli slot disponibili
        foreach ($timeSlots as $slot) {
            $startTime = Carbon::createFromFormat('H:i:s', $slot->start_time);
            $endTime = Carbon::createFromFormat('H:i:s', $slot->end_time);

            $currentTime = $startTime->copy();

            while ($currentTime->copy()->addMinutes($serviceDuration) <= $endTime) {
                $slotStart = $currentTime->copy();
                $slotEnd = $slotStart->copy()->addMinutes($serviceDuration);

                $isAvailable = true;

                // Controlla overlap con booking esistenti
                foreach ($bookings as $booking) {
                    // Estrai solo l'ora dal booking
                    $bookingTime = $booking->time instanceof \DateTime
                        ? $booking->time->format('H:i:s')
                        : $booking->time;

                    $bookingStart = Carbon::createFromFormat('H:i:s', $bookingTime);
                    $bookingEnd = $bookingStart->copy()->addMinutes($booking->service->duration);

                    if (
                        ($slotStart >= $bookingStart && $slotStart < $bookingEnd) ||
                        ($slotEnd > $bookingStart && $slotEnd <= $bookingEnd) ||
                        ($slotStart <= $bookingStart && $slotEnd >= $bookingEnd)
                    ) {
                        $isAvailable = false;
                        break;
                    }
                }

                // ✅ NUOVO: Controlla se lo slot è chiuso (closed_slots)
                if ($isAvailable) {
                    foreach ($closedSlots as $closed) {
                        $closedTime = $closed->time;

                        // Se il giorno intero è chiuso (time = null)
                        if ($closedTime === null) {
                            $isAvailable = false;
                            break;
                        }

                        // Se un orario specifico è chiuso
                        $closedStart = Carbon::createFromFormat('H:i', $closedTime);
                        if ($slotStart->format('H:i') === $closedStart->format('H:i')) {
                            $isAvailable = false;
                            break;
                        }
                    }
                }

                if ($isAvailable) {
                    $allSlots[] = $slotStart->format('H:i');
                }

                $currentTime->addMinutes($serviceDuration);
            }
        }

        // ===========================
        // FILTRA ORARI PASSATI SE OGGI
        // ===========================
        $now = Carbon::now();
        $today = $now->toDateString();
        $currentTime = $now->format('H:i');

        if ($date == $today) {
            // Se la data è oggi, rimuovi gli slot passati
            $allSlots = array_filter($allSlots, function ($slot) use ($currentTime) {
                return $slot >= $currentTime;
            });
        }

        return response()->json([
            'status' => true,
            'slots' => array_values(array_unique($allSlots)),
            'service_duration' => $serviceDuration,
            'date' => $date,
            'staff_id' => $staffId,
            'closed_slots' => $closedSlots, // 🆕 Includi i closed slots con motivi
        ]);
    }
}
