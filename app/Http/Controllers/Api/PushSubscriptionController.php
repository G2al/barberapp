<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Notifications\PushTestNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class PushSubscriptionController extends Controller
{
    public function config(): JsonResponse
    {
        $publicKey = config('webpush.vapid.public_key');
        $configured = filled($publicKey) && filled(config('webpush.vapid.private_key'));

        return response()->json([
            'status' => true,
            'supported' => $configured,
            'public_key' => $configured ? $publicKey : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!filled(config('webpush.vapid.public_key')) || !filled(config('webpush.vapid.private_key'))) {
            return response()->json([
                'status' => false,
                'message' => 'Le notifiche push non sono ancora configurate.',
            ], 503);
        }

        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:500', 'url:https'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', Rule::in(['aes128gcm', 'aesgcm'])],
        ]);

        $subscription = $request->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['content_encoding'] ?? 'aes128gcm',
        );

        return response()->json([
            'status' => true,
            'message' => 'Notifiche attivate su questo dispositivo.',
            'subscription_id' => $subscription->getKey(),
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        $request->user()->deletePushSubscription($validated['endpoint']);

        return response()->json([
            'status' => true,
            'message' => 'Notifiche disattivate su questo dispositivo.',
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        if (!filled(config('webpush.vapid.public_key')) || !filled(config('webpush.vapid.private_key'))) {
            return response()->json([
                'status' => false,
                'message' => 'Le notifiche push non sono ancora configurate.',
            ], 503);
        }

        if (!$request->user()->pushSubscriptions()->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Attiva prima le notifiche su questo dispositivo.',
            ], 422);
        }

        Notification::sendNow(
            $request->user(),
            new PushTestNotification(),
        );

        return response()->json([
            'status' => true,
            'message' => 'Notifica di prova inviata.',
        ]);
    }
}
