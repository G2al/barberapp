<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class BookingReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Booking $booking;
    public string $type;

    /**
     * Create a new notification instance.
     */
    public function __construct(Booking $booking, string $type = '1h')
    {
        $this->booking = $booking;
        $this->type = $type;
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
            ->title('Il tuo appuntamento si avvicina')
            ->body("{$date} alle {$time}: {$service} con {$staff}.")
            ->icon('/images/logo-192x192.png')
            ->tag('booking-reminder-'.$this->booking->id.'-'.$this->type)
            ->data(['url' => '/my-bookings.html'])
            ->options(['TTL' => 3600]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->booking->loadMissing(['staff', 'service']);

        $title = match ($this->type) {
            '24h' => 'Ci vediamo domani',
            '3h' => 'Mancano meno di 3 ore',
            default => 'Manca meno di 1 ora',
        };

        $reminderText = match ($this->type) {
            '24h' => 'La tua prenotazione e&apos; prevista per domani. Ti aspettiamo da Giovanni Cerino Hair Stylist.',
            '3h' => 'La tua prenotazione e&apos; in arrivo: mancano meno di 3 ore.',
            default => 'La tua prenotazione e&apos; molto vicina: mancano meno di 1 ora.',
        };

        return (new MailMessage)
            ->subject("Reminder prenotazione - {$title}")
            ->view('emails.booking-reminder', [
                'name' => $notifiable->name,
                'title' => $title,
                'reminderText' => $reminderText,
                'date' => $this->booking->date->format('d/m/Y'),
                'time' => substr($this->booking->time, 0, 5),
                'service' => $this->booking->service->name ?? 'N/A',
                'staff' => $this->booking->staff->first_name . ' ' . $this->booking->staff->last_name,
                'heroImage' => asset('images/booking-reminder.png'),
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
            'type' => 'booking_reminder',
            'reminder_type' => $this->type,
        ];
    }
}
