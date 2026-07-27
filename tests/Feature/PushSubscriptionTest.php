<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\PushTestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('webpush.vapid.subject', 'https://alettabarber2k24.it');
        config()->set('webpush.vapid.public_key', 'public-test-key');
        config()->set('webpush.vapid.private_key', 'private-test-key');
        config()->set('features.push_notifications', true);
    }

    public function test_push_routes_require_authentication(): void
    {
        $this->getJson('/api/push/config')->assertUnauthorized();
        $this->postJson('/api/push/subscriptions')->assertUnauthorized();
        $this->deleteJson('/api/push/subscriptions')->assertUnauthorized();
        $this->postJson('/api/push/test')->assertUnauthorized();
    }

    public function test_authenticated_user_can_store_and_delete_a_subscription(): void
    {
        $user = User::factory()->create([
            'surname' => 'Test',
            'phone' => '3330000001',
        ]);
        Sanctum::actingAs($user);

        $subscription = [
            'endpoint' => 'https://push.example.test/subscriptions/device-1',
            'keys' => [
                'p256dh' => 'device-public-key',
                'auth' => 'device-auth-token',
            ],
            'content_encoding' => 'aes128gcm',
        ];

        $this->getJson('/api/push/config')
            ->assertOk()
            ->assertJsonPath('supported', true)
            ->assertJsonPath('public_key', 'public-test-key');

        $this->postJson('/api/push/subscriptions', $subscription)
            ->assertCreated()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_type' => User::class,
            'subscribable_id' => $user->id,
            'endpoint' => $subscription['endpoint'],
            'content_encoding' => 'aes128gcm',
        ]);

        $this->deleteJson('/api/push/subscriptions', [
            'endpoint' => $subscription['endpoint'],
        ])
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => $subscription['endpoint'],
        ]);
    }

    public function test_push_config_stays_disabled_until_both_keys_exist(): void
    {
        $user = User::factory()->create([
            'surname' => 'Test',
            'phone' => '3330000004',
        ]);
        Sanctum::actingAs($user);
        config()->set('webpush.vapid.private_key', null);

        $this->getJson('/api/push/config')
            ->assertOk()
            ->assertJsonPath('supported', false)
            ->assertJsonPath('public_key', null);
    }

    public function test_push_config_can_be_disabled_by_feature_flag(): void
    {
        $user = User::factory()->create([
            'surname' => 'Test',
            'phone' => '3330000006',
        ]);
        Sanctum::actingAs($user);
        config()->set('features.push_notifications', false);

        $this->getJson('/api/push/config')
            ->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('supported', false)
            ->assertJsonPath('public_key', null);

        $this->postJson('/api/push/subscriptions', [
            'endpoint' => 'https://push.example.test/subscriptions/device-disabled',
            'keys' => [
                'p256dh' => 'device-public-key',
                'auth' => 'device-auth-token',
            ],
            'content_encoding' => 'aes128gcm',
        ])
            ->assertForbidden()
            ->assertJsonPath('status', false);
    }

    public function test_push_test_requires_an_active_subscription(): void
    {
        $user = User::factory()->create([
            'surname' => 'Test',
            'phone' => '3330000002',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/push/test')
            ->assertUnprocessable()
            ->assertJsonPath('status', false);
    }

    public function test_authenticated_user_can_request_a_test_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'surname' => 'Test',
            'phone' => '3330000003',
        ]);
        Sanctum::actingAs($user);

        $user->updatePushSubscription(
            'https://push.example.test/subscriptions/device-2',
            'device-public-key',
            'device-auth-token',
            'aes128gcm',
        );

        $this->postJson('/api/push/test')
            ->assertOk()
            ->assertJsonPath('status', true);

        Notification::assertSentTo($user, PushTestNotification::class);
    }

    public function test_booking_notification_adds_web_push_only_for_subscribed_users(): void
    {
        $user = User::factory()->create([
            'surname' => 'Test',
            'phone' => '3330000005',
        ]);
        $notification = new BookingConfirmedNotification(new Booking());

        $this->assertSame(['mail'], $notification->via($user));

        $user->updatePushSubscription(
            'https://push.example.test/subscriptions/device-3',
            'device-public-key',
            'device-auth-token',
            'aes128gcm',
        );

        $this->assertContains(WebPushChannel::class, $notification->via($user));
    }
}
