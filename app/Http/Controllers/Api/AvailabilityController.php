<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Staff;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    /**
     * GET /api/availability/{staffId}?date={date}&serviceId={serviceId}
     */
    public function getSlots($staffId, Request $request, AvailabilityService $availabilityService)
    {
        $date = $request->query('date');
        $serviceId = $request->query('serviceId');

        if (! $date || ! $serviceId) {
            return response()->json([
                'status' => false,
                'message' => 'date e serviceId sono obbligatori',
            ], 400);
        }

        $staff = Staff::findOrFail($staffId);
        $service = Service::findOrFail($serviceId);

        if (! $staff->services()->where('service_id', $serviceId)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Questo staff non offre questo servizio',
            ], 400);
        }

        $availability = $availabilityService->availableSlots($staff, $service, $date);

        if (! $availability['has_schedule']) {
            return response()->json([
                'status' => true,
                'slots' => [],
                'message' => 'Nessun orario disponibile per questo giorno',
            ]);
        }

        $response = [
            'status' => true,
            'slots' => $availability['slots'],
            'service_duration' => $availability['service_duration'],
            'date' => $date,
            'staff_id' => $staffId,
            'closed_slots' => $availability['closed_slots'],
        ];

        return response()->json($response);
    }
}
