<?php

namespace Tests\Unit;

use App\Services\Ai\Prompts\LamaBarberAssistantPrompt;
use PHPUnit\Framework\TestCase;

class LamaBarberAssistantPromptTest extends TestCase
{
    public function test_availability_response_rules_prevent_contradictory_or_redundant_text(): void
    {
        $instructions = (new LamaBarberAssistantPrompt)->instructions();

        $this->assertStringContainsString('completo, reale e prioritario', $instructions);
        $this->assertStringContainsString('non usare mai la frase "Questa informazione non e\' disponibile"', $instructions);
        $this->assertStringContainsString('non sceglierne uno autonomamente', $instructions);
        $this->assertStringContainsString('Vuoi prenotare?', $instructions);
        $this->assertStringContainsString('Non mostrare date tecniche nel formato YYYY-MM-DD', $instructions);
        $this->assertStringContainsString('Non menzionare alternative quando lo slot richiesto e\' disponibile', $instructions);
    }
}
