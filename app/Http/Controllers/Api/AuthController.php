<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

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

    /**
     * FORGOT PASSWORD (invio link reset)
     */
    public function forgotPassword(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            ['email' => 'required|email']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Invia il link (risposta generica per non esporre la presenza dell'email)
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'status' => true,
            'message' => 'Se l\'email è registrata, ti abbiamo inviato il link di reset.',
        ]);
    }

    /**
     * RESET PASSWORD (usa token email)
     */
    public function resetPassword(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'token'                 => 'required',
                'email'                 => 'required|email',
                'password'              => 'required|min:6|confirmed',
            ],
            [
                'password.confirmed' => 'Le password non coincidono.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                // Revoca eventuali token API attivi per sicurezza
                if (method_exists($user, 'tokens')) {
                    $user->tokens()->delete();
                }

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'status' => true,
                'message' => 'Password reimpostata con successo. Ora puoi accedere con la nuova password.',
            ]);
        }

        // Token scaduto o non valido
        return response()->json([
            'status' => false,
            'message' => 'Link non valido o scaduto. Richiedi un nuovo reset.',
        ], 400);
    }
}
