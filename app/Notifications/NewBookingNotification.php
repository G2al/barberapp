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
        $escape = static fn ($value): string => e((string) $value);
        $note = filled($booking->note)
            ? "\n📝 <b>Nota:</b> {$escape($booking->note)}"
            : '';

        return "✅ <b>PRENOTAZIONE CONFERMATA</b>\n\n" .
            "👤 <b>Cliente:</b> {$escape($booking->user->name)}\n" .
            "📞 <b>Telefono:</b> {$escape($booking->user->phone)}\n" .
            "💈 <b>Barbiere:</b> {$escape($booking->staff->full_name)}\n" .
            "✂️ <b>Servizio:</b> {$escape($booking->service->name)}\n" .
            "📆 <b>Data:</b> " . $escape(\Carbon\Carbon::parse($booking->date)->format('d/m/Y')) . "\n" .
            "🕒 <b>Ora:</b> {$escape(substr((string) $booking->time, 0, 5))}\n" .
            $note . "\n" .
            "💶 <b>Prezzo:</b> EUR {$escape(number_format((float) $booking->service->price, 2, '.', ''))}\n" .
            "✅ <b>Stato:</b> Confermata";
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
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[
                        [
                            'text' => '🔗 Apri pannello prenotazioni',
                            'url' => 'https://admin.mottolasfamily.it/admin/bookings',
                        ],
                    ]],
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
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
