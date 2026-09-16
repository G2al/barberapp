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
        if (config('webpush.enabled') && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /**
     * Get the push representation of the notification.
     */
    public function toWebPush(object $notifiable): WebPushMessage
    {
        $this->booking->loadMissing(['staff', 'service']);
        $date = $this->booking->date->format('d/m');
        $time = substr($this->booking->time, 0, 5);
        $service = $this->booking->service?->name ?? 'Appuntamento';
        $staff = trim(($this->booking->staff?->first_name ?? '').' '.($this->booking->staff?->last_name ?? ''));

        return (new WebPushMessage)
            ->title('Prenotazione confermata')
            ->body("{$date} alle {$time}: {$service} con {$staff}.")
            ->icon('/images/gabriele-del-piano-icon-192.png')
            ->tag('booking-confirmed-'.$this->booking->id)
            ->data(['url' => '/my-bookings.html'])
            ->options(['TTL' => 3600]);
    }

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
                'heroImage' => asset('images/gabriele-del-piano-email-banner.png'),
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
