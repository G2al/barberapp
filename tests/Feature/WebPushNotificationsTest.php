<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\BookingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class WebPushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_register_and_remove_a_push_subscription(): void
    {
        $this->enableWebPush();

        $user = User::factory()->create([
            'surname' => 'Cliente',
            'phone' => '3331112233',
        ]);
        Sanctum::actingAs($user);

        $endpoint = 'https://push.example.test/subscriptions/browser-1';

        $this->postJson('/api/push/subscriptions', [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'public-key',
                'auth' => 'auth-token',
            ],
            'content_encoding' => 'aes128gcm',
        ])->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_type' => User::class,
            'subscribable_id' => $user->id,
            'endpoint' => $endpoint,
        ]);

        $this->deleteJson('/api/push/subscriptions', [
            'endpoint' => $endpoint,
        ])->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => $endpoint,
        ]);
    }

    public function test_subscription_registration_is_unavailable_when_web_push_is_disabled(): void
    {
        config(['webpush.enabled' => false]);

        $user = User::factory()->create([
            'surname' => 'Cliente',
            'phone' => '3331112244',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/push/subscriptions', [
            'endpoint' => 'https://push.example.test/subscriptions/browser-2',
            'keys' => [
                'p256dh' => 'public-key',
                'auth' => 'auth-token',
            ],
        ])->assertNotFound();
    }

    public function test_booking_notification_uses_web_push_only_when_enabled(): void
    {
        $notification = new BookingConfirmedNotification(new Booking());

        config(['webpush.enabled' => false]);
        $this->assertSame(['mail'], $notification->via(new User()));

        config(['webpush.enabled' => true]);
        $this->assertContains(WebPushChannel::class, $notification->via(new User()));
    }

    public function test_booking_push_messages_are_personalized_for_each_event(): void
    {
        $booking = new Booking([
            'date' => '2026-07-29',
            'time' => '09:30:00',
        ]);
        $booking->id = 15;
        $booking->setRelation('service', new Service(['name' => 'Taglio + Shampoo']));
        $booking->setRelation('staff', new Staff([
            'first_name' => 'Giovanni',
            'last_name' => 'Cerino',
        ]));

        $confirmed = (new BookingConfirmedNotification($booking))->toWebPush(new User())->toArray();
        $cancelled = (new BookingCancelledNotification($booking))->toWebPush(new User())->toArray();
        $reminder24h = (new BookingReminderNotification($booking, '24h'))->toWebPush(new User())->toArray();
        $reminder3h = (new BookingReminderNotification($booking, '3h'))->toWebPush(new User())->toArray();
        $reminder1h = (new BookingReminderNotification($booking, '1h'))->toWebPush(new User())->toArray();

        $this->assertSame('✅ Prenotazione confermata', $confirmed['title']);
        $this->assertSame('❌ Prenotazione annullata', $cancelled['title']);
        $this->assertSame('📅 Ci vediamo domani', $reminder24h['title']);
        $this->assertSame('⏳ Il tuo appuntamento si avvicina', $reminder3h['title']);
        $this->assertSame('⏰ Manca meno di un’ora', $reminder1h['title']);
        $this->assertStringContainsString('09:30', $confirmed['body']);
    }

    private function enableWebPush(): void
    {
        config([
            'webpush.enabled' => true,
            'webpush.vapid.subject' => 'mailto:test@example.com',
            'webpush.vapid.public_key' => 'public-vapid-key',
            'webpush.vapid.private_key' => 'private-vapid-key',
        ]);
    }
}
