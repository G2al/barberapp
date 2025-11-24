<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class NewBookingNotification extends Notification
{
    use Queueable;

    protected $booking;

    /**
     * Create a new notification instance.
     */
    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['telegram'];
    }

    /**
     * Send the notification via Telegram.
     */
    public function toTelegram(object $notifiable): void
    {
        $booking = $this->booking->load(['user', 'staff', 'service']);

        $message = $this->formatMessage($booking);

        $this->sendTelegramMessage($message);
    }

    /**
     * Format the booking message.
     */
    private function formatMessage(Booking $booking): string
    {
        return "📅 NUOVA PRENOTAZIONE\n\n" .
            "👤 Cliente: {$booking->user->name}\n" .
            "📞 Telefono: {$booking->user->phone}\n" .
            "💈 Barbiere: {$booking->staff->full_name}\n" .
            "✂️ Servizio: {$booking->service->name}\n" .
            "📆 Data: " . \Carbon\Carbon::parse($booking->date)->format('d/m/Y') . "\n" .
            "⏰ Ora: {$booking->time}\n" .
            "💰 Prezzo: €{$booking->service->price}\n" .
            "✅ Status: Confermata\n\n" .
            "🔗 Vai all'admin: " . url('/admin/bookings');
    }

    /**
     * Send message to Telegram.
     */
    private function sendTelegramMessage(string $message): void
    {
        $token = config('services.telegram.bot_token') ?? env('TELEGRAM_BOT_TOKEN');
        $chatId = config('services.telegram.chat_id') ?? env('TELEGRAM_CHAT_ID');

        if (!$token || !$chatId) {
            \Log::error('Telegram config missing', ['token' => $token, 'chatId' => $chatId]);
            return;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

            \Log::info('Telegram response', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Telegram error', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 10) . '...',
                'chatId' => $chatId,
            ]);
        }
    }
}
