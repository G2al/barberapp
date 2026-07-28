<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'user' => $this->formatUser($request->user()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->merge([
            'phone' => preg_replace('/\D/', '', (string) $request->input('phone')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'surname' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
        ]);

        $user->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Profilo aggiornato.',
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La password attuale non è corretta.'],
            ]);
        }

        $user->update(['password' => $validated['password']]);

        return response()->json([
            'status' => true,
            'message' => 'Password aggiornata.',
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $validated['avatar']->store('user-avatars', 'public');
        $user->update(['avatar' => $path]);

        return response()->json([
            'status' => true,
            'message' => 'Foto profilo aggiornata.',
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    private function formatUser($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'surname' => $user->surname,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'avatar' => $user->avatar,
            'avatar_url' => $user->avatar ? Storage::url($user->avatar) : null,
        ];
    }
}
