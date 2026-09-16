<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingWaitlistEntry;
use App\Services\BookingWaitlistService;
use Illuminate\Http\Request;

class BookingWaitlistController extends Controller
{
    public function index(Request $request)
    {
        $entries = BookingWaitlistEntry::with(['staff', 'service', 'assignedBooking'])
            ->where('user_id', $request->user()->id)
            ->where(function ($query) {
                $query->where('status', 'waiting')
                    ->orWhere(function ($query) {
                        $query->where('status', 'assigned')
                            ->whereHas('assignedBooking');
                    });
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(function (BookingWaitlistEntry $entry) {
                $position = $entry->status === 'waiting'
                    ? BookingWaitlistEntry::where('staff_id', $entry->staff_id)
                        ->whereDate('date', substr((string) $entry->getRawOriginal('date'), 0, 10))
                        ->where('time', 'like', substr((string) $entry->time, 0, 5).'%')
                        ->where('status', 'waiting')
                        ->where('id', '<=', $entry->id)
                        ->count()
                    : null;

                return [
                    'id' => $entry->id,
                    'staff' => ['id' => $entry->staff_id, 'name' => trim($entry->staff->first_name.' '.$entry->staff->last_name)],
                    'service' => ['id' => $entry->service_id, 'name' => $entry->service->name],
                    'date' => substr((string) $entry->getRawOriginal('date'), 0, 10),
                    'time' => substr((string) $entry->time, 0, 5),
                    'status' => $entry->status,
                    'position' => $position,
                    'booking_id' => $entry->assigned_booking_id,
                ];
            });

        return response()->json(['status' => true, 'entries' => $entries]);
    }

    public function store(Request $request, BookingWaitlistService $waitlist)
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
        ]);

        $result = $waitlist->join(
            $request->user()->id,
            $validated['staff_id'],
            $validated['service_id'],
            $validated['date'],
            $validated['time'],
        );

        if (isset($result['error'])) {
            return response()->json(['status' => false, 'message' => $result['error']], $result['status']);
        }

        if ($result['entry']->status === 'assigned') {
            return response()->json([
                'status' => false,
                'message' => 'Questo appuntamento è già stato assegnato dal sistema.',
                'booking_id' => $result['entry']->assigned_booking_id,
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'Sei in lista d’attesa per questo orario.',
            'entry' => [
                'id' => $result['entry']->id,
                'staff' => ['id' => $result['entry']->staff->id, 'name' => trim($result['entry']->staff->first_name.' '.$result['entry']->staff->last_name)],
                'service' => ['id' => $result['entry']->service->id, 'name' => $result['entry']->service->name],
                'date' => substr((string) $result['entry']->getRawOriginal('date'), 0, 10),
                'time' => substr((string) $result['entry']->time, 0, 5),
                'status' => $result['entry']->status,
                'position' => $result['position'],
            ],
        ], 201);
    }

    public function destroy(Request $request, BookingWaitlistEntry $entry)
    {
        abort_unless($entry->user_id === $request->user()->id, 404);

        if ($entry->status !== 'waiting') {
            return response()->json(['status' => false, 'message' => 'Questa iscrizione non è più in attesa.'], 409);
        }

        $entry->update(['status' => 'cancelled']);

        return response()->json(['status' => true, 'message' => 'Iscrizione rimossa dalla lista d’attesa.']);
    }
}
