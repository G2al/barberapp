<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * REGISTER
     */
    public function register(Request $request)
    {
        // Normalizzazione telefono (toglie tutto tranne i numeri)
        $cleanPhone = preg_replace('/\D/', '', $request->phone);

        // Inserisci il nuovo phone normalizzato *dentro* la request
        $request->merge(['phone' => $cleanPhone]);

        $validator = \Validator::make(
            $request->all(),
            [
                'name'     => 'required|string|max:100',
                'surname'  => 'required|string|max:100',
                'email'    => 'required|email|unique:users,email',
                'phone'    => 'required|string|max:20|unique:users,phone',
                'password' => 'required|min:6',
            ],
            [
                'phone.unique' => 'Questo numero di telefono è già registrato.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'surname'  => $request->surname,
            'email'    => $request->email,
            'phone'    => $request->phone, // già normalizzato
            'password' => Hash::make($request->password),
            'role'     => 'client',
            'is_active'=> true,
        ]);

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Registrazione avvenuta con successo.',
            'token' => $token,
            'user' => $user,
        ]);
    }


    /**
     * LOGIN
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'Inserisci la tua email.',
            'email.email' => 'Formato email non valido.',
            'password.required' => 'Inserisci la password.'
        ]);

        $user = User::where('email', $request->email)->first();

        // User not found
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Email o password non validi.',
            ], 401);
        }

        // User banned
        if (!$user->is_active) {
            return response()->json([
                'status' => false,
                'message' => 'Utente disattivato. Contatta l\'amministratore.',
            ], 403);
        }

        // Password wrong
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Email o password non validi.',
            ], 401);
        }

        // Login OK
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Accesso effettuato.',
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * LOGOUT
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logout effettuato.',
        ]);
    }
}
