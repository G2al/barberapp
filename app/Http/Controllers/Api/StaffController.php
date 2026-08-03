<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    /**
     * 🔹 Restituisce TUTTO lo staff attivo
     */
    public function index(Request $request)
    {
        return Staff::where('is_active', true)
            ->when(
                in_array($request->query('department'), ['hair', 'beauty'], true),
                fn ($query) => $query->where('department', $request->query('department'))
            )
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'role', 'department', 'phone', 'image'])
            ->map(function ($staff) {
                $staff->image_url = $staff->image ? \Illuminate\Support\Facades\Storage::url($staff->image) : null;
                return $staff;
            });
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
            ->get(['id', 'first_name', 'last_name', 'role', 'department', 'phone', 'image'])
            ->map(function ($staff) {
                $staff->image_url = $staff->image ? \Illuminate\Support\Facades\Storage::url($staff->image) : null;
                return $staff;
            });
    }
}
