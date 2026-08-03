<?php

namespace Tests\Unit;

use App\Services\Ai\OpenAiChatService;
use Tests\TestCase;

class OpenAiChatServiceTest extends TestCase
{
    public function test_cost_is_zero_when_prices_are_not_configured(): void
    {
        config()->set('ai.pricing.input_per_million', null);
        config()->set('ai.pricing.output_per_million', null);

        $service = app(OpenAiChatService::class);

        $this->assertSame(0.0, $service->estimateCost([
            'input_tokens' => 1000,
            'output_tokens' => 100,
        ]));
    }

    public function test_cost_uses_configured_prices(): void
    {
        config()->set('ai.pricing.input_per_million', 0.20);
        config()->set('ai.pricing.output_per_million', 1.25);

        $service = app(OpenAiChatService::class);

        $this->assertSame(0.000325, $service->estimateCost([
            'input_tokens' => 1000,
            'output_tokens' => 100,
        ]));
    }
}
