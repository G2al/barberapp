<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BookingCancelledTelegramNotification extends Notification
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
        $escape = static fn ($value): string => e((string) $value);
        $date = \Carbon\Carbon::parse($booking->date)->format('d/m/Y');
        $time = substr((string) $booking->time, 0, 5);

        $message = "❌ <b>PRENOTAZIONE ANNULLATA</b>\n\n" .
            "👤 <b>Cliente:</b> {$escape($booking->user?->name)}\n" .
            "📞 <b>Telefono:</b> {$escape($booking->user?->phone)}\n" .
            "💈 <b>Barbiere:</b> {$escape($booking->staff?->full_name)}\n" .
            "✂️ <b>Servizio:</b> {$escape($booking->service?->name)}\n" .
            "📆 <b>Data:</b> {$escape($date)}\n" .
            "🕒 <b>Ora:</b> {$escape($time)}\n" .
            "🔴 <b>Stato:</b> Annullata\n\n" .
            "⚠️ Lo slot è nuovamente disponibile.";

        $this->sendTelegramMessage($message);
    }

    private function sendTelegramMessage(string $message): void
    {
        $token = config('services.telegram.bot_token') ?? env('TELEGRAM_BOT_TOKEN');
        $chatId = config('services.telegram.chat_id') ?? env('TELEGRAM_CHAT_ID');

        if (!$token || !$chatId) {
            Log::error('Telegram config missing for cancellation notification', [
                'chatId' => $chatId,
            ]);
            return;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[
                        [
                            'text' => '🔗 Apri pannello prenotazioni',
                            'url' => 'https://admin.gabrieledelpianobarber.it/admin/bookings',
                        ],
                    ]],
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);

            Log::info('Telegram cancellation response', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Telegram cancellation notification failed', [
                'booking_id' => $this->booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
