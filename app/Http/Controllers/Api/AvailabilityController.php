<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Availability;
use App\Models\Booking;
use App\Models\ClosedSlot;
use App\Models\Service;
use App\Models\Staff;
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

        $availabilities = Availability::where('weekday', $weekday)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        if ($availabilities->isEmpty()) {
            return response()->json([
                'status' => true,
                'slots' => [],
                'message' => 'Salone chiuso questo giorno'
            ]);
        }

        $bookings = Booking::where('staff_id', $staffId)
            ->where('date', $date)
            ->orderBy('time')
            ->get();

        // ✅ NUOVO: Carica i closed slots per questo staff e data
        $closedSlots = ClosedSlot::where('staff_id', $staffId)
            ->where('date', $date)
            ->get();

        $serviceDuration = $service->duration;
        $allSlots = [];

        // Genera tutti gli slot disponibili
        foreach ($availabilities as $availability) {
            $startTime = Carbon::createFromFormat('H:i:s', $availability->start_time);
            $endTime = Carbon::createFromFormat('H:i:s', $availability->end_time);

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