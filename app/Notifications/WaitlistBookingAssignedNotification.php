<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class WaitlistBookingAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        $channels = ['mail'];
        if (config('webpush.enabled') && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        $this->booking->loadMissing(['staff', 'service']);
        $date = $this->booking->date->format('d/m');
        $time = substr((string) $this->booking->time, 0, 5);

        return (new WebPushMessage)
            ->title('Si è liberato il tuo appuntamento')
            ->body("{$date} alle {$time}: {$this->booking->service->name} con {$this->booking->staff->first_name}.")
            ->icon('/images/gabriele-del-piano-icon-192.png')
            ->tag('waitlist-assigned-'.$this->booking->id)
            ->data(['url' => '/my-bookings.html'])
            ->options(['TTL' => 3600]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->booking->loadMissing(['staff', 'service']);

        return (new MailMessage)
            ->subject('Appuntamento assegnato dalla lista d’attesa')
            ->greeting('Ciao '.$notifiable->name)
            ->line('Si è liberato lo slot che aspettavi e abbiamo confermato automaticamente il tuo appuntamento.')
            ->line('Servizio: '.$this->booking->service->name)
            ->line('Professionista: '.$this->booking->staff->first_name.' '.$this->booking->staff->last_name)
            ->line('Data e ora: '.$this->booking->date->format('d/m/Y').' alle '.substr((string) $this->booking->time, 0, 5))
            ->action('Le mie prenotazioni', url('/my-bookings.html'));
    }

    public function toArray(object $notifiable): array
    {
        return ['booking_id' => $this->booking->id, 'type' => 'waitlist_booking_assigned'];
    }
}
