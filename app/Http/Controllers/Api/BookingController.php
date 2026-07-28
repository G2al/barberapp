<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Staff;
use App\Models\Service;
use App\Notifications\NewBookingNotification;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\BookingCancelledNotification;
use App\Services\BookingReminderService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            'note' => 'nullable|string|max:1000',
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
            ->whereIn('status', ['pending', 'confirmed'])
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
            'note' => $user->role === 'admin' ? ($validated['note'] ?? null) : null,
        ]);

        // 📱 Invia notifica Telegram all'admin
        try {
            Notification::route('telegram', env('TELEGRAM_CHAT_ID'))
                ->notify(new NewBookingNotification($booking));
        } catch (\Throwable $e) {
            Log::error('Booking Telegram notification failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }

        // 📧 Invia email di conferma all'utente
        try {
            $user->notify(new BookingConfirmedNotification($booking));
        } catch (\Throwable $e) {
            Log::error('Booking confirmation notification failed', [
                'booking_id' => $booking->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Invia subito il reminder se la prenotazione nasce gia' dentro una fascia utile.
        try {
            $reminderType = app(BookingReminderService::class)->sendDueReminder($booking);

            if ($reminderType !== null) {
                Log::info('Immediate booking reminder sent', [
                    'booking_id' => $booking->id,
                    'user_id' => $user->id,
                    'type' => $reminderType,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Immediate booking reminder failed', [
                'booking_id' => $booking->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

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
                'note' => $booking->note,
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
                    'staff_image' => $booking->staff->image ? \Illuminate\Support\Facades\Storage::url($booking->staff->image) : null,
                    'service' => $booking->service->name,
                    'service_duration' => $booking->service->duration,
                    'date' => $booking->date,
                    'time' => $booking->time,
                    'status' => $booking->status,
                    'note' => $booking->note,
                    'created_at' => $booking->created_at,
                ];
            });


        return response()->json([
            'status' => true,
            'bookings' => $bookings
        ]);
    }

    /**
     * POST /api/bookings/{id}/cancel
     * Annulla una prenotazione dell'utente, liberando lo slot
     */
    public function cancel(Request $request, $id)
    {
        $user = $request->user();

        $booking = Booking::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Consenti cancellazione solo se ancora attiva
        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return response()->json([
                'status' => false,
                'message' => 'Questa prenotazione non può essere annullata.',
            ], 400);
        }

        // Blocca cancellazioni su prenotazioni già passate
        try {
            $bookingDate = $booking->date instanceof \Carbon\Carbon
                ? $booking->date->format('Y-m-d')
                : $booking->date;

            $bookingDateTime = Carbon::parse("{$bookingDate} {$booking->time}");

            if ($bookingDateTime->isPast()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Non puoi annullare una prenotazione già trascorsa.',
                ], 400);
            }
        } catch (\Exception $e) {
            \Log::error('Errore parsing data/ora in cancellazione booking', [
                'id' => $booking->id,
                'date' => $booking->date,
                'time' => $booking->time,
                'error' => $e->getMessage(),
            ]);
        }

        $booking->update(['status' => 'cancelled']);

        try {
            $user->notify(new BookingCancelledNotification($booking));
        } catch (\Throwable $e) {
            Log::error('Booking cancellation notification failed', [
                'booking_id' => $booking->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Prenotazione annullata con successo.',
            'booking' => [
                'id' => $booking->id,
                'status' => $booking->status,
                'date' => $booking->date,
                'time' => $booking->time,
            ],
        ]);
    }
}
