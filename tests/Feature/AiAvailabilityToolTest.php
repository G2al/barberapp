<?php

namespace Tests\Feature;

use App\Models\Availability;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Services\Ai\Tools\CheckAvailabilityTool;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiAvailabilityToolTest extends TestCase
{
    use RefreshDatabase;

    private Service $service;

    private Staff $staff;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-06 10:00:00', 'Europe/Rome'));
        config()->set([
            'app.timezone' => 'Europe/Rome',
            'ai.enabled' => true,
            'ai.api_key' => 'test-key-not-secret',
            'ai.model' => 'gpt-5.4-nano',
            'ai.base_url' => 'https://api.openai.test/v1',
            'ai.context_cache_seconds' => 0,
            'ai.rate_limits.minute' => 100,
            'ai.rate_limits.hour' => 100,
            'ai.rate_limits.day' => 100,
        ]);

        $this->service = Service::create([
            'name' => 'Barba Experience',
            'description' => 'Servizio barba',
            'price' => 20,
            'duration' => 30,
            'loyalty_points' => 10,
            'is_active' => true,
        ]);
        $this->staff = Staff::create([
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'phone' => '3880000000',
            'is_active' => true,
        ]);
        $this->staff->services()->attach($this->service);
        $this->customer = User::factory()->create([
            'name' => 'ClientePrivato',
            'surname' => 'Segreto',
            'email' => 'cliente-privato@example.test',
            'phone' => '3990000000',
            'role' => 'user',
            'is_active' => true,
        ]);

        Availability::create([
            'weekday' => Carbon::THURSDAY,
            'slot_type' => 'afternoon',
            'start_time' => '15:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);
        Booking::create([
            'user_id' => $this->customer->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => '2026-08-06',
            'time' => '15:00',
            'status' => 'confirmed',
            'note' => 'Nota privata della prenotazione',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_available_slot_is_checked_with_real_availability_logic(): void
    {
        $arguments = $this->arguments(time: '15:30');
        $result = $this->tool()->execute($arguments);

        $this->assertTrue($result['available']);
        $this->assertSame('15:30', $result['requested_slot']);
        $this->assertSame('Mario Rossi', $result['professional']);
        $this->assertSame([], $result['alternatives']);
        $this->assertSame('confirm_booking', $this->tool()->bookingAction($arguments, $result)['type']);
    }

    public function test_occupied_slot_returns_three_nearest_real_alternatives(): void
    {
        $arguments = $this->arguments(time: '15:00');
        $result = $this->tool()->execute($arguments);

        $this->assertFalse($result['available']);
        $this->assertSame(['15:30', '16:00', '16:30'], $result['alternatives']);
        $this->assertNull($this->tool()->bookingAction($arguments, $result));
    }

    public function test_unknown_service_is_not_resolved(): void
    {
        $arguments = $this->arguments(time: '15:30');
        $arguments['service_name'] = 'Servizio inesistente';

        $result = $this->tool()->execute($arguments);

        $this->assertFalse($result['available']);
        $this->assertNull($result['service']);
        $this->assertSame([], $result['alternatives']);
    }

    public function test_staff_is_optional_and_an_active_compatible_professional_is_selected(): void
    {
        $result = $this->tool()->execute($this->arguments(time: null));

        $this->assertTrue($result['available']);
        $this->assertSame('Mario Rossi', $result['professional']);
        $this->assertSame(['15:30', '16:00', '16:30'], $result['alternatives']);
    }

    public function test_shared_public_api_uses_the_same_availability_service(): void
    {
        $this->getJson("/api/availability/{$this->staff->id}?date=2026-08-06&serviceId={$this->service->id}")
            ->assertOk()
            ->assertJsonPath('slots.0', '15:30')
            ->assertJsonMissing(['15:00']);
    }

    public function test_tool_is_read_only_and_cannot_create_or_modify_bookings(): void
    {
        $before = $this->bookingSnapshot();

        $this->tool()->execute($this->arguments(time: '15:00'));

        $after = $this->bookingSnapshot();
        $this->assertSame($before, $after);
        $this->assertSame(CheckAvailabilityTool::NAME, $this->tool()->definition()['name']);
    }

    public function test_ai_returns_confirmable_action_without_creating_booking_automatically(): void
    {
        Sanctum::actingAs($this->customer);
        Notification::fake();
        $call = 0;

        Http::preventStrayRequests();
        Http::fake(function () use (&$call) {
            $call++;

            if ($call === 1) {
                return Http::response([
                    'output' => [[
                        'type' => 'function_call',
                        'name' => CheckAvailabilityTool::NAME,
                        'call_id' => 'call_availability_1',
                        'arguments' => json_encode($this->arguments(time: '15:30')),
                    ]],
                    'usage' => ['input_tokens' => 100, 'output_tokens' => 20, 'total_tokens' => 120],
                ]);
            }

            return Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'Si, giovedi 6 agosto alle 15:30 c e disponibilita con Mario Rossi. Vuoi prenotare?',
                    ]],
                ]],
                'usage' => ['input_tokens' => 130, 'output_tokens' => 30, 'total_tokens' => 160],
            ]);
        });

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Barba Experience giovedi alle 15:30 e disponibile?',
        ])->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('action.type', 'confirm_booking')
            ->assertJsonPath('action.method', 'POST')
            ->assertJsonPath('action.url', '/api/bookings')
            ->assertJsonPath('action.payload.staff_id', $this->staff->id)
            ->assertJsonPath('action.payload.service_id', $this->service->id)
            ->assertJsonPath('action.payload.date', '2026-08-06')
            ->assertJsonPath('action.payload.time', '15:30');

        $this->assertDatabaseCount('bookings', 1);

        $this->postJson('/api/bookings', $response->json('action.payload'))
            ->assertCreated()
            ->assertJsonPath('status', true);

        $this->assertDatabaseCount('bookings', 2);

        $this->assertSame(2, $call);
        Http::assertSent(function (Request $request): bool {
            $payload = json_encode($request->data());

            return ! str_contains($payload, 'ClientePrivato')
                && ! str_contains($payload, 'cliente-privato@example.test')
                && ! str_contains($payload, '3990000000')
                && ! str_contains($payload, 'Nota privata della prenotazione');
        });

        Http::assertSent(fn (Request $request): bool => isset($request['tools'][0])
            && $request['tools'][0]['name'] === CheckAvailabilityTool::NAME
            && $request['tools'][0]['strict'] === true);
        Http::assertSent(fn (Request $request): bool => ! isset($request['tools'])
            && str_contains(json_encode($request->data()), 'function_call_output'));
    }

    public function test_missing_date_produces_one_short_clarification_without_tool_call(): void
    {
        Sanctum::actingAs($this->customer);
        Http::fake(['*' => Http::response([
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => 'Per quale data vuoi verificare la disponibilita?',
                ]],
            ]],
            'usage' => ['input_tokens' => 50, 'output_tokens' => 10, 'total_tokens' => 60],
        ])]);

        $this->postJson('/api/ai/chat', ['message' => 'C e posto per Barba Experience?'])
            ->assertOk()
            ->assertJsonPath('answer', 'Per quale data vuoi verificare la disponibilita?');

        Http::assertSentCount(1);
    }

    private function tool(): CheckAvailabilityTool
    {
        return app(CheckAvailabilityTool::class);
    }

    private function arguments(?string $time): array
    {
        return [
            'date' => '2026-08-06',
            'time' => $time,
            'service_id' => null,
            'service_name' => 'Barba Experience',
            'staff_id' => null,
            'staff_name' => null,
        ];
    }

    private function bookingSnapshot(): array
    {
        return Booking::query()->get()->map(fn (Booking $booking): array => [
            'id' => $booking->id,
            'status' => $booking->status,
            'date' => $booking->date->toDateString(),
            'time' => $booking->time,
        ])->all();
    }
}
