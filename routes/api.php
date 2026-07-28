<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controllers
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\ClosedSlotController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\ProfileController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Tutte le API dell'app mobile BarberApp
|--------------------------------------------------------------------------
*/

/* =========================
   🔹 TEST API
========================= */
Route::get('/test', fn () => response()->json(['message' => 'API working']));

Route::get('/app-config', fn () => response()->json([
    'location' => config('barbershop.location'),
]));

// Test Telegram notification
Route::get('/test-telegram', function () {
    $booking = \App\Models\Booking::latest()->first();
    if (!$booking) {
        return response()->json(['status' => false, 'message' => 'No bookings found']);
    }

    \Illuminate\Support\Facades\Notification::route('telegram', env('TELEGRAM_CHAT_ID'))
        ->notify(new \App\Notifications\NewBookingNotification($booking));

    return response()->json(['status' => true, 'message' => 'Telegram notification sent!']);
});


/* =========================
   🔹 AUTH
========================= */
Route::prefix('auth')->group(function () {
    // Login & Register
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/reset-password',  [AuthController::class, 'resetPassword']);

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::put('/password', [ProfileController::class, 'updatePassword']);
        Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
    });
});


/* =========================
   🔹 SERVICES
========================= */
// Tutti i servizi attivi
Route::get('/services', [ServiceController::class, 'index']);

Route::get('/services/by-staff/{staffId}', [ServiceController::class, 'byStaff']);

// Dettaglio singolo servizio (se serve in futuro)
Route::get('/services/{id}', [ServiceController::class, 'show']);


/* =========================
   🔹 STAFF
========================= */
// Tutto lo staff attivo
Route::get('/staff', [StaffController::class, 'index']);

// Staff filtrato per servizio (barbieri che fanno quel servizio)
Route::get('/staff/by-service/{serviceId}', [StaffController::class, 'byService']);

/* =========================
   �Y"� PRODUCTS & FAVORITES (Protette)
========================= */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/{product}', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{product}', [FavoriteController::class, 'destroy']);
});

/* =========================
   LOYALTY (Protette)
========================= */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/loyalty/summary', [LoyaltyController::class, 'summary']);
    Route::post('/loyalty/rewards/{reward}/redeem', [LoyaltyController::class, 'redeem']);
});


/* =========================
   🔹 AVAILABILITY
========================= */
// Slot disponibili per uno staff in una data
Route::get('/availability/{staffId}', [AvailabilityController::class, 'getSlots']);

// Giorni chiusi per uno staff (PUBLIC - per il frontend)
Route::get('/staff/{staff}/closed-slots-public', [ClosedSlotController::class, 'getByStaffAndDate']);


/* =========================
   🔹 BOOKINGS (Protette)
========================= */
Route::middleware('auth:sanctum')->group(function () {
    // Crea una nuova prenotazione
    Route::post('/bookings', [BookingController::class, 'store']);

    // Leggi prenotazioni dell'utente
    Route::get('/bookings', [BookingController::class, 'index']);

    // Annulla una prenotazione dell'utente
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
});


/* =========================
   🔹 CLOSED SLOTS (Admin)
========================= */
Route::middleware('auth:sanctum')->group(function () {
    // Crea un nuovo closed slot
    Route::post('/closed-slots', [ClosedSlotController::class, 'store']);

    // Leggi closed slots di uno staff
    Route::get('/staff/{staff}/closed-slots', [ClosedSlotController::class, 'index']);

    // Dettaglio closed slot
    Route::get('/closed-slots/{closedSlot}', [ClosedSlotController::class, 'show']);

    // Aggiorna closed slot
    Route::put('/closed-slots/{closedSlot}', [ClosedSlotController::class, 'update']);

    // Elimina closed slot
    Route::delete('/closed-slots/{closedSlot}', [ClosedSlotController::class, 'destroy']);

    // Tutti i closed slots (admin view)
    Route::get('/closed-slots', [ClosedSlotController::class, 'all']);
});
