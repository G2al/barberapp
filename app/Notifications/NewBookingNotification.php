<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NewBookingNotification extends Notification
{
    use Queueable;

    public function __construct(protected Booking $booking)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['telegram'];
    }

    public function toTelegram(object $notifiable): void
    {
        $booking = $this->booking->load(['user', 'staff', 'service']);

        $this->sendTelegramMessage($this->formatMessage($booking));
    }

    private function formatMessage(Booking $booking): string
    {
        $note = filled($booking->note) ? "Nota: {$booking->note}\n" : '';

        return "NUOVA PRENOTAZIONE\n\n" .
            "Cliente: {$booking->user->name}\n" .
            "Telefono: {$booking->user->phone}\n" .
            "Barbiere: {$booking->staff->full_name}\n" .
            "Servizio: {$booking->service->name}\n" .
            "Data: " . \Carbon\Carbon::parse($booking->date)->format('d/m/Y') . "\n" .
            "Ora: {$booking->time}\n" .
            $note .
            "Prezzo: EUR {$booking->service->price}\n" .
            "Status: Confermata\n\n" .
            "Vai all'admin: " . url('/admin/bookings');
    }

    private function sendTelegramMessage(string $message): void
    {
        $token = config('services.telegram.bot_token') ?? env('TELEGRAM_BOT_TOKEN');
        $chatId = config('services.telegram.chat_id') ?? env('TELEGRAM_CHAT_ID');

        if (!$token || !$chatId) {
            Log::error('Telegram config missing', ['token' => $token, 'chatId' => $chatId]);
            return;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
            ]);

            Log::info('Telegram response', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        } catch (\Exception $e) {
            Log::error('Telegram error', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 10) . '...',
                'chatId' => $chatId,
            ]);
        }
    }
}
