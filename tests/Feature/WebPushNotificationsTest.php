<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\BookingReminderNotification;
use App\Notifications\WaitlistBookingAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class WebPushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        // Test migrations must never use the application's local or production database.
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('webpush.database_connection', 'sqlite');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'webpush.enabled' => true,
            'webpush.vapid.subject' => 'mailto:test@example.test',
            'webpush.vapid.public_key' => 'public-test-key',
            'webpush.vapid.private_key' => 'private-test-key',
        ]);
    }

    private function user(): User
    {
        return User::factory()->create(['surname' => 'Test', 'phone' => fake()->unique()->numerify('333#######')]);
    }

    private function subscription(): array
    {
        return [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-token'],
        ];
    }

    public function test_authentication_and_disabled_configuration(): void
    {
        $this->getJson('/api/push/config')->assertUnauthorized();
        $this->postJson('/api/push/subscriptions', $this->subscription())->assertUnauthorized();
        Sanctum::actingAs($this->user());
        config()->set('webpush.enabled', false);
        $this->getJson('/api/push/config')->assertOk()->assertJson(['enabled' => false, 'public_key' => null]);
        $this->postJson('/api/push/subscriptions', $this->subscription())->assertNotFound();
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_registration_is_idempotent_private_key_is_never_returned_and_deletion_is_scoped(): void
    {
        $owner = $this->user();
        Sanctum::actingAs($owner);
        $this->getJson('/api/push/config')->assertOk()->assertJsonMissingPath('private_key')
            ->assertDontSee('private-test-key');
        $this->postJson('/api/push/subscriptions', $this->subscription())->assertOk();
        $this->postJson('/api/push/subscriptions', $this->subscription())->assertOk();
        $this->assertDatabaseCount('push_subscriptions', 1);
        Sanctum::actingAs($this->user());
        $this->deleteJson('/api/push/subscriptions', $this->subscription())->assertOk();
        $this->assertDatabaseCount('push_subscriptions', 1);
        Sanctum::actingAs($owner);
        $this->deleteJson('/api/push/subscriptions', $this->subscription())->assertOk();
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_push_endpoints_cannot_target_internal_or_arbitrary_servers(): void
    {
        Sanctum::actingAs($this->user());
        foreach (['http://127.0.0.1', 'https://example.com', 'https://fcm.googleapis.com.evil.test/send'] as $endpoint) {
            $data = $this->subscription();
            $data['endpoint'] = $endpoint;
            $this->postJson('/api/push/subscriptions', $data)->assertUnprocessable();
        }
    }

    public function test_all_booking_events_keep_email_and_add_push_only_with_subscription(): void
    {
        $user = $this->user();
        $booking = new Booking(['id' => 42, 'date' => '2026-09-08', 'time' => '15:00:00']);
        $booking->setRelation('service', new Service(['name' => 'Taglio']));
        $booking->setRelation('staff', new Staff(['first_name' => 'Gabriele', 'last_name' => 'Del Piano']));
        $events = [
            new BookingConfirmedNotification($booking),
            new BookingCancelledNotification($booking),
            new BookingReminderNotification($booking, '24h'),
            new BookingReminderNotification($booking, '3h'),
            new BookingReminderNotification($booking, '1h'),
            new WaitlistBookingAssignedNotification($booking),
        ];
        foreach ($events as $event) {
            $this->assertSame(['mail'], $event->via($user));
        }
        Sanctum::actingAs($user);
        $this->postJson('/api/push/subscriptions', $this->subscription())->assertOk();
        foreach ($events as $event) {
            $this->assertSame(['mail', WebPushChannel::class], $event->via($user));
            $payload = $event->toWebPush($user)->toArray();
            $this->assertStringContainsString('08/09 alle 15:00', $payload['body']);
            $this->assertSame('/my-bookings.html', $payload['data']['url']);
            $this->assertStringNotContainsString($user->email, json_encode($payload));
        }
        config()->set('webpush.enabled', false);
        foreach ($events as $event) {
            $this->assertSame(['mail'], $event->via($user));
        }
    }
}
