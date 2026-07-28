<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingConfirmedNotification;
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
