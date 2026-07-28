<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;

class ServiceController extends Controller
{
    /**
     * 🔹 Restituisce tutti i servizi attivi
     */
    public function index()
    {
        return Service::where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'duration']);
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
            ->get(['id', 'name', 'price', 'duration']);
    }

    public function show($id)
    {
        return Service::select('id', 'name', 'price', 'duration', 'description')
            ->findOrFail($id);
    }
}
