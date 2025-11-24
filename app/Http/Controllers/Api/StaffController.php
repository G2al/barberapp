<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;

class StaffController extends Controller
{
    /**
     * 🔹 Restituisce TUTTO lo staff attivo
     */
    public function index()
    {
        return Staff::where('is_active', true)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'role', 'phone']);
    }

    /**
     * 🔹 Restituisce lo staff che offre un determinato servizio
     */
    public function byService($serviceId)
    {
        return Staff::where('is_active', 1)
            ->whereHas('services', function ($q) use ($serviceId) {
                $q->where('services.id', $serviceId);
            })
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'role', 'phone']);
    }
}
