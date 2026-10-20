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

    public function __construct(private Booking $booking)
    {
    }

    /**
     * Send the cancellation message to the configured salon Telegram chat.
     */
    public function via(object $notifiable): array
    {
        return ['telegram'];
    }

    public function toTelegram(object $notifiable): void
    {
        $booking = $this->booking->load(['user', 'staff', 'service']);
        $escape = static fn ($value): string => e((string) $value);
        $date = $booking->date instanceof \Carbon\Carbon
            ? $booking->date->format('d/m/Y')
            : \Carbon\Carbon::parse($booking->date)->format('d/m/Y');

        $message = "❌ <b>PRENOTAZIONE ANNULLATA</b>\n\n" .
            "👤 <b>Cliente:</b> {$escape($booking->user?->name)}\n" .
            "📞 <b>Telefono:</b> {$escape($booking->user?->phone)}\n" .
            "💈 <b>Barbiere:</b> {$escape($booking->staff?->full_name)}\n" .
            "✂️ <b>Servizio:</b> {$escape($booking->service?->name)}\n" .
            "📆 <b>Data:</b> {$escape($date)}\n" .
            "🕒 <b>Ora:</b> {$escape(substr((string) $booking->time, 0, 5))}\n" .
            "🔴 <b>Stato:</b> Annullata\n\n" .
            "⚠️ Lo slot è nuovamente disponibile.";

        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (!$token || !$chatId) {
            Log::warning('Telegram cancellation notification skipped: configuration missing.');
            return;
        }

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[
                        [
                            'text' => '🔗 Apri pannello prenotazioni',
                            'url' => 'https://admin.mottolasfamily.it/admin/bookings',
                        ],
                    ]],
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ])->throw();
        } catch (\Throwable $e) {
            Log::error('Booking cancellation Telegram notification failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
