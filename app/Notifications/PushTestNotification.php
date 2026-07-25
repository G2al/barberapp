<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PushTestNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Aletta Barber')
            ->body('Le notifiche sono attive su questo dispositivo.')
            ->icon('/images/logo-192x192.png')
            ->badge('/images/logo-192x192.png')
            ->tag('webpush-test')
            ->data([
                'url' => '/dashboard.html',
                'type' => 'test',
            ])
            ->options([
                'TTL' => 300,
                'urgency' => 'normal',
            ]);
    }
}
