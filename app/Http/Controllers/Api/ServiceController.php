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
        return Service::where('is_active', 1)
            ->when(
                in_array($request->query('department'), ['hair', 'beauty'], true),
                fn ($query) => $query->where('department', $request->query('department'))
            )
            ->orderBy('name')
            ->get(['id', 'name', 'department', 'price', 'price_type', 'duration']);
    }

    /**
     * 🔹 Restituisce un singolo servizio (opzionale per futuro)
     */
    public function byStaff($staffId)
    {
        return Service::where('is_active', 1)
            ->whereHas('staff', function ($query) use ($staffId) {
                $query->where('staff.id', $staffId);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'department', 'price', 'price_type', 'duration']);
    }

    public function show($id)
    {
        return Service::select('id', 'name', 'department', 'price', 'price_type', 'duration', 'description')
            ->findOrFail($id);
    }
}
