<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'ai.enabled' => true,
            'ai.api_key' => 'test-key-not-secret',
            'ai.model' => 'gpt-5.4-nano',
            'ai.base_url' => 'https://api.openai.test/v1',
            'ai.context_cache_seconds' => 0,
            'ai.special_days_ahead' => 30,
            'ai.rate_limits.minute' => 100,
            'ai.rate_limits.hour' => 100,
            'ai.rate_limits.day' => 100,
            'ai.pricing.input_per_million' => 0.20,
            'ai.pricing.output_per_million' => 1.25,
        ]);
    }

    public function test_unauthenticated_user_cannot_use_ai_chat(): void
    {
        Http::preventStrayRequests();

        $this->postJson('/api/ai/chat', ['message' => 'Quali servizi offrite?'])
            ->assertUnauthorized();
    }

    public function test_inactive_user_cannot_use_ai_chat(): void
    {
        $user = $this->user(['is_active' => false]);
        Sanctum::actingAs($user);
        Http::preventStrayRequests();

        $this->postJson('/api/ai/chat', ['message' => 'Quali servizi offrite?'])
            ->assertForbidden()
            ->assertJsonPath('status', false);
    }

    public function test_disabled_feature_returns_service_unavailable_without_external_call(): void
    {
        config()->set('ai.enabled', false);
        $user = $this->user();
        Sanctum::actingAs($user);
        Http::preventStrayRequests();

        $this->postJson('/api/ai/chat', ['message' => 'Quali servizi offrite?'])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'feature_disabled');

        $this->assertDatabaseHas('ai_request_logs', [
            'user_id' => $user->id,
            'success' => false,
            'error_code' => 'feature_disabled',
        ]);
    }

    public function test_message_is_required_trimmed_and_limited(): void
    {
        Sanctum::actingAs($this->user());
        Http::preventStrayRequests();

        $this->postJson('/api/ai/chat', ['message' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');

        $this->postJson('/api/ai/chat', ['message' => str_repeat('a', 801)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_valid_response_parses_usage_calculates_cost_and_logs_only_technical_data(): void
    {
        $admin = $this->user([
            'name' => 'PrivateNameForContextTest',
            'email' => 'private-context@example.test',
            'phone' => '3999999999',
            'role' => 'admin',
        ]);
        Sanctum::actingAs($admin);

        $service = Service::create([
            'name' => 'Taglio prova',
            'description' => 'Servizio pubblico',
            'price' => 20,
            'duration' => 30,
            'loyalty_points' => 10,
            'is_active' => true,
        ]);
        $staff = Staff::create([
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'phone' => '3888888888',
            'is_active' => true,
        ]);
        $staff->services()->attach($service);
        Booking::create([
            'user_id' => $admin->id,
            'staff_id' => $staff->id,
            'service_id' => $service->id,
            'date' => today()->addDay(),
            'time' => '10:00',
            'status' => 'confirmed',
            'note' => 'NotaPrivataDaNonInviare',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response($this->validOpenAiResponse(), 200),
        ]);

        $this->postJson('/api/ai/chat', ['message' => 'Quanto costa il taglio?'])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('answer', 'Il taglio costa 20 euro.')
            ->assertJsonPath('usage.input_tokens', 1000)
            ->assertJsonPath('usage.output_tokens', 100)
            ->assertJsonPath('usage.total_tokens', 1100)
            ->assertJsonPath('usage.estimated_cost_usd', 0.000325);

        Http::assertSent(function (Request $request): bool {
            $payload = json_encode($request->data());

            return $request->url() === 'https://api.openai.test/v1/responses'
                && $request['store'] === false
                && $request['model'] === 'gpt-5.4-nano'
                && str_contains($payload, 'Taglio prova')
                && str_contains($payload, 'Mario Rossi')
                && ! str_contains($payload, 'PrivateNameForContextTest')
                && ! str_contains($payload, 'private-context@example.test')
                && ! str_contains($payload, '3999999999')
                && ! str_contains($payload, '3888888888')
                && ! str_contains($payload, 'NotaPrivataDaNonInviare');
        });

        $this->assertDatabaseHas('ai_request_logs', [
            'user_id' => $admin->id,
            'model' => 'gpt-5.4-nano',
            'success' => true,
            'input_tokens' => 1000,
            'output_tokens' => 100,
            'total_tokens' => 1100,
        ]);
        $this->assertFalse(Schema::hasColumn('ai_request_logs', 'message'));
        $this->assertFalse(Schema::hasColumn('ai_request_logs', 'answer'));
        $this->assertFalse(Schema::hasColumn('ai_request_logs', 'prompt'));
    }

    public function test_normal_user_does_not_receive_usage(): void
    {
        Sanctum::actingAs($this->user(['role' => 'user']));
        Http::fake(['*' => Http::response($this->validOpenAiResponse(), 200)]);

        $this->postJson('/api/ai/chat', ['message' => 'Quali servizi offrite?'])
            ->assertOk()
            ->assertJsonMissingPath('usage');
    }

    public function test_connection_timeout_returns_safe_error_and_is_logged(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);
        Http::fake(fn () => throw new ConnectionException('Sensitive upstream detail'));

        $this->postJson('/api/ai/chat', ['message' => 'Quali servizi offrite?'])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'connection_error')
            ->assertJsonMissing(['Sensitive upstream detail']);

        $this->assertDatabaseHas('ai_request_logs', [
            'user_id' => $user->id,
            'success' => false,
            'error_code' => 'connection_error',
        ]);
    }

    public function test_openai_rate_limit_returns_429_without_internal_details(): void
    {
        Sanctum::actingAs($this->user());
        Http::fake(['*' => Http::response([
            'error' => ['code' => 'rate_limit_exceeded', 'message' => 'Internal detail'],
        ], 429)]);

        $this->postJson('/api/ai/chat', ['message' => 'Quali servizi offrite?'])
            ->assertTooManyRequests()
            ->assertJsonPath('error_code', 'openai_rate_limited')
            ->assertJsonMissing(['Internal detail']);
    }

    public function test_malformed_openai_response_is_rejected(): void
    {
        Sanctum::actingAs($this->user());
        Http::fake(['*' => Http::response(['output' => []], 200)]);

        $this->postJson('/api/ai/chat', ['message' => 'Quali servizi offrite?'])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'invalid_response');
    }

    public function test_application_rate_limit_is_per_user(): void
    {
        config()->set('ai.rate_limits.minute', 1);
        $user = $this->user();
        Sanctum::actingAs($user);
        $this->clearAiRateLimits($user);
        Http::fake(['*' => Http::response($this->validOpenAiResponse(), 200)]);

        $this->postJson('/api/ai/chat', ['message' => 'Prima domanda'])->assertOk();
        $this->postJson('/api/ai/chat', ['message' => 'Seconda domanda'])->assertTooManyRequests();
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'surname' => 'Cliente',
            'phone' => '3331112223',
            'role' => 'user',
            'is_active' => true,
        ], $attributes));
    }

    private function validOpenAiResponse(): array
    {
        return [
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => 'Il taglio costa 20 euro.',
                ]],
            ]],
            'usage' => [
                'input_tokens' => 1000,
                'output_tokens' => 100,
                'total_tokens' => 1100,
            ],
        ];
    }

    private function clearAiRateLimits(User $user): void
    {
        foreach (['minute', 'hour', 'day'] as $window) {
            RateLimiter::clear('user:'.$user->id.':'.$window);
        }
    }
}
