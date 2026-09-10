<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * 🔹 Restituisce tutti i servizi attivi
     */
    public function index(Request $request)
    {
        $query = Service::where('is_active', 1);

        if ($request->user()) {
            $query->whereDoesntHave('userRestrictions', function ($restrictionQuery) use ($request) {
                $restrictionQuery->where('user_id', $request->user()->id)
                    ->whereNull('staff_id');
            });

            if ($request->filled('staff_id')) {
                $staffId = (int) $request->query('staff_id');
                $query->whereDoesntHave('userRestrictions', function ($restrictionQuery) use ($request, $staffId) {
                    $restrictionQuery->where('user_id', $request->user()->id)
                        ->where(function ($staffQuery) use ($staffId) {
                            $staffQuery->whereNull('staff_id')->orWhere('staff_id', $staffId);
                        });
                });
            } else {
                // Without a selected staff, keep a service only if at least
                // one active staff member can still perform it for this user.
                $query->whereHas('staff', function ($staffQuery) use ($request) {
                    $staffQuery->where('is_active', true)
                        ->whereDoesntHave('serviceRestrictions', function ($restrictionQuery) use ($request) {
                            $restrictionQuery->where('user_id', $request->user()->id)
                                ->where(function ($staffRestrictionQuery) {
                                    $staffRestrictionQuery
                                        ->whereNull('staff_id')
                                        ->orWhereColumn('staff_id', 'staff.id');
                                });
                        });
                });
            }
        }

        return $query
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'duration']);
    }

    /**
     * 🔹 Restituisce un singolo servizio (opzionale per futuro)
     */
    public function show($id)
    {
        return Service::select('id', 'name', 'price', 'duration', 'description')
            ->findOrFail($id);
    }
}
