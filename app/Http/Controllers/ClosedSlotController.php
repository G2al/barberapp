<?php

namespace App\Http\Controllers;

use App\Models\ClosedSlot;
use App\Models\Staff;
use App\Http\Requests\ClosedSlotRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClosedSlotController extends Controller
{
    /**
     * Display a listing of closed slots for a staff member
     */
    public function index(Staff $staff): JsonResponse
    {
        $closedSlots = ClosedSlot::with('staff')
            ->appliesToStaff($staff->id)
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'data' => $closedSlots,
            'message' => 'Closed slots retrieved successfully',
        ]);
    }

    /**
     * Store a newly created closed slot in storage
     */
    public function store(ClosedSlotRequest $request): JsonResponse
    {
        $closedSlot = ClosedSlot::create($request->validated());

        return response()->json([
            'data' => $closedSlot,
            'message' => 'Closed slot created successfully',
        ], 201);
    }

    /**
     * Display the specified closed slot
     */
    public function show(ClosedSlot $closedSlot): JsonResponse
    {
        return response()->json([
            'data' => $closedSlot->load('staff'),
            'message' => 'Closed slot retrieved successfully',
        ]);
    }

    /**
     * Update the specified closed slot
     */
    public function update(ClosedSlotRequest $request, ClosedSlot $closedSlot): JsonResponse
    {
        $closedSlot->update($request->validated());

        return response()->json([
            'data' => $closedSlot,
            'message' => 'Closed slot updated successfully',
        ]);
    }

    /**
     * Remove the specified closed slot from storage
     */
    public function destroy(ClosedSlot $closedSlot): JsonResponse
    {
        $closedSlot->delete();

        return response()->json([
            'message' => 'Closed slot deleted successfully',
        ]);
    }

    /**
     * Get all closed slots (admin view)
     */
    public function all(): JsonResponse
    {
        $closedSlots = ClosedSlot::with('staff')
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'data' => $closedSlots,
            'message' => 'All closed slots retrieved successfully',
        ]);
    }

    /**
     * Get closed slots for a staff member in a date range (PUBLIC - for frontend)
     */
    public function getByStaffAndDate(Staff $staff, Request $request): JsonResponse
    {
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $query = ClosedSlot::with('staff')
            ->appliesToStaff($staff->id);

        if ($toDate) {
            $query->whereDate('date', '<=', $toDate);
        }

        if ($fromDate) {
            $query->where(function ($query) use ($fromDate) {
                $query->where(function ($query) use ($fromDate) {
                    $query->whereNull('end_date')
                        ->whereDate('date', '>=', $fromDate);
                })->orWhereDate('end_date', '>=', $fromDate);
            });
        }

        $closedSlots = $query->orderBy('date')->get();

        return response()->json([
            'data' => $closedSlots,
            'message' => 'Closed slots retrieved successfully',
        ]);
    }
}
