<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class BookingConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Booking $booking;

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
        $channels = ['mail'];

        if (config('webpush.enabled')) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->booking->loadMissing(['staff', 'service']);

        return (new MailMessage)
            ->subject('Prenotazione confermata')
            ->view('emails.booking-confirmed', [
                'name' => $notifiable->name,
                'date' => $this->booking->date->format('d/m/Y'),
                'time' => substr($this->booking->time, 0, 5),
                'service' => $this->booking->service->name ?? 'N/A',
                'staff' => $this->booking->staff->first_name . ' ' . $this->booking->staff->last_name,
                'heroImage' => asset('images/stile-infinito-logo-white.png'),
            ]);
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        $this->booking->loadMissing(['staff', 'service']);

        return (new WebPushMessage)
            ->title('✅ Prenotazione confermata')
            ->body(sprintf(
                '💈 %s con %s, il %s alle %s. Ti aspettiamo!',
                $this->booking->service->name ?? 'Appuntamento',
                trim(($this->booking->staff->first_name ?? '').' '.($this->booking->staff->last_name ?? '')),
                $this->booking->date->format('d/m/Y'),
                substr((string) $this->booking->time, 0, 5),
            ))
            ->icon(asset('images/logo-192x192.png'))
            ->badge(asset('images/maskable-icon-192x192.png'))
            ->tag("booking-{$this->booking->id}")
            ->vibrate([150, 75, 150])
            ->data([
                'url' => '/my-bookings.html',
                'booking_id' => $this->booking->id,
                'type' => 'booking_confirmed',
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'type' => 'booking_confirmed',
        ];
    }
}
