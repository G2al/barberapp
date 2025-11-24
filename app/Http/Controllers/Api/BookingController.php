<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Staff;
use App\Models\Service;
use App\Notifications\NewBookingNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class BookingController extends Controller
{
    /**
     * POST /api/bookings
     * Crea una nuova prenotazione
     */
    public function store(Request $request)
    {
        // ✅ Validazione input
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'service_id' => 'required|exists:services,id',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'haircut_id' => 'nullable|exists:haircuts,id',
        ]);

        $user = $request->user();
        $staffId = $validated['staff_id'];
        $serviceId = $validated['service_id'];
        $date = $validated['date'];
        $time = $validated['time'];

        // ✅ Verifica che lo staff offra questo servizio
        $staff = Staff::findOrFail($staffId);
        $service = Service::findOrFail($serviceId);

        if (!$staff->services()->where('service_id', $serviceId)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Questo staff non offre questo servizio'
            ], 400);
        }

        // ✅ Log di debug per verificare i dati
        \Log::info('DEBUG BOOKING DATE-TIME', [
            'date' => $date,
            'time' => $time,
            'concat' => "$date $time"
        ]);

        // ✅ Calcolo dello slot richiesto
        $slotStart = Carbon::createFromFormat('Y-m-d H:i', "$date $time");
        $slotEnd = $slotStart->copy()->addMinutes($service->duration);

        // ✅ Verifica overlap con altre prenotazioni dello stesso staff
        $hasOverlap = false;
        $bookings = Booking::where('staff_id', $staffId)
            ->where('date', $date)
            ->get();

        foreach ($bookings as $booking) {
            // 🧩 Normalizza i valori da DB (evita doppie date)
            $bookingDate = Carbon::parse($booking->date)->format('Y-m-d');
            $bookingTime = substr((string) $booking->time, 0, 5); // forza formato H:i

            try {
                $bookingStart = Carbon::createFromFormat('Y-m-d H:i', "$bookingDate $bookingTime");
                $bookingEnd = $bookingStart->copy()->addMinutes($booking->service->duration);
            } catch (\Exception $e) {
                \Log::error('BOOKING PARSE ERROR', [
                    'id' => $booking->id,
                    'date' => $booking->date,
                    'time' => $booking->time,
                    'error' => $e->getMessage(),
                ]);
                continue; // ignora booking malformate
            }

            // 🔍 Controllo overlap
            if (
                ($slotStart >= $bookingStart && $slotStart < $bookingEnd) ||
                ($slotEnd > $bookingStart && $slotEnd <= $bookingEnd) ||
                ($slotStart <= $bookingStart && $slotEnd >= $bookingEnd)
            ) {
                $hasOverlap = true;
                break;
            }
        }

        if ($hasOverlap) {
            return response()->json([
                'status' => false,
                'message' => 'Questo slot non è più disponibile'
            ], 400);
        }

        // ✅ Verifica che lo slot non sia chiuso
        if (!Booking::isSlotAvailable($staffId, $date, $time)) {
            return response()->json([
                'status' => false,
                'message' => 'Questo giorno o orario è chiuso'
            ], 400);
        }

        // ✅ NUOVO: Verifica limite prenotazioni attive
        if (!Booking::canUserBookMore($user->id)) {
            $maxBookings = \App\Models\Setting::get('max_active_bookings', 3);
            $activeCount = Booking::countActiveBookings($user->id);
            return response()->json([
                'status' => false,
                'message' => "Hai raggiunto il limite di $maxBookings prenotazioni attive. Attendi che una scada prima di prenotarne un'altra.",
                'active_bookings' => $activeCount,
                'max_bookings' => $maxBookings
            ], 400);
        }

        // ✅ Salva la prenotazione
        $booking = Booking::create([
            'user_id' => $user->id,
            'staff_id' => $staffId,
            'service_id' => $serviceId,
            'haircut_id' => $validated['haircut_id'] ?? null,
            'date' => $date,
            'time' => $time,
            'status' => 'confirmed',
        ]);

        // 📱 Invia notifica Telegram all'admin
        Notification::route('telegram', env('TELEGRAM_CHAT_ID'))
            ->notify(new NewBookingNotification($booking));

        return response()->json([
            'status' => true,
            'message' => 'Prenotazione effettuata con successo',
            'booking' => [
                'id' => $booking->id,
                'user_id' => $booking->user_id,
                'staff' => $booking->staff->first_name . ' ' . $booking->staff->last_name,
                'service' => $booking->service->name,
                'date' => $booking->date,
                'time' => $booking->time,
                'status' => $booking->status,
                'created_at' => $booking->created_at,
            ]
        ], 201);
    }

    /**
     * GET /api/bookings
     * Ritorna le prenotazioni dell'utente loggato
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $bookings = Booking::where('user_id', $user->id)
            ->orderBy('date', 'desc')
            ->orderBy('time', 'desc')
            ->get()
            ->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'staff' => $booking->staff->first_name . ' ' . $booking->staff->last_name,
                    'staff_phone' => $booking->staff->phone,
                    'service' => $booking->service->name,
                    'date' => $booking->date,
                    'time' => $booking->time,
                    'status' => $booking->status,
                    'created_at' => $booking->created_at,
                ];
            });


        return response()->json([
            'status' => true,
            'bookings' => $bookings
        ]);
    }
}
