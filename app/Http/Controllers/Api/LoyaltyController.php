<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyReward;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function summary(Request $request, LoyaltyService $loyalty): JsonResponse
    {
        return response()->json([
            'status' => true,
            'loyalty' => $loyalty->summaryFor($request->user()),
        ]);
    }

    public function redeem(Request $request, LoyaltyReward $reward, LoyaltyService $loyalty): JsonResponse
    {
        if ($reward->user_id !== $request->user()->id) {
            abort(404);
        }

        try {
            $reward = $loyalty->redeem($reward);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Premio segnato come usato. Mostra il codice in cassa.',
            'reward' => [
                'id' => $reward->id,
                'title' => $reward->title,
                'status' => $reward->status,
                'code' => $reward->code,
                'redeemed_at' => $reward->redeemed_at?->toIso8601String(),
            ],
        ]);
    }
}
