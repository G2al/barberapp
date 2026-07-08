<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->booking->date->format('d/m');
        $time = substr($this->booking->time, 0, 5);
        $service = $this->booking->service->name ?? 'N/A';
        $staff = $this->booking->staff->first_name . ' ' . $this->booking->staff->last_name;
        $message = match ($this->type) {
            '24h' => "Promemoria: la tua prenotazione e' domani.",
            '3h' => "Promemoria: la tua prenotazione e' tra meno di 3 ore.",
            default => "Promemoria: la tua prenotazione e' tra meno di 1 ora.",
        };

        return (new MailMessage)
            ->greeting("Ciao {$notifiable->name},")
            ->line($message)
            ->line("**Data:** {$date}")
            ->line("**Ora:** {$time}")
            ->line("**Servizio:** {$service}")
            ->line("**Staff:** {$staff}")
            ->line('Presentati puntuale.')
            ->line('')
            ->line('Cordiali saluti,')
            ->line('La Barberia Di Salvatore Nappa');
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
