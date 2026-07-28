<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PushSubscriptionController extends Controller
{
    public function config(): JsonResponse
    {
        $enabled = $this->isConfigured();

        return response()->json([
            'enabled' => $enabled,
            'public_key' => $enabled ? config('webpush.vapid.public_key') : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->isConfigured(), 404);

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', Rule::in(['aesgcm', 'aes128gcm'])],
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['content_encoding'] ?? 'aes128gcm',
        );

        return response()->json([
            'status' => true,
            'message' => 'Notifiche push attivate.',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->json([
            'status' => true,
            'message' => 'Notifiche push disattivate.',
        ]);
    }

    private function isConfigured(): bool
    {
        return (bool) config('webpush.enabled')
            && filled(config('webpush.vapid.subject'))
            && filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }
}
