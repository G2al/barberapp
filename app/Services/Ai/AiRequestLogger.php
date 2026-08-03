<?php

namespace App\Services\Ai;

use App\Models\AiRequestLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiRequestLogger
{
    public function success(User $user, array $result): void
    {
        $usage = $result['usage'] ?? [];

        $this->write([
            'user_id' => $user->id,
            'model' => $result['model'] ?? null,
            'success' => true,
            'http_status' => $result['upstream_status'] ?? 200,
            'input_tokens' => $usage['input_tokens'] ?? 0,
            'output_tokens' => $usage['output_tokens'] ?? 0,
            'total_tokens' => $usage['total_tokens'] ?? 0,
            'estimated_cost_usd' => $result['estimated_cost_usd'] ?? 0,
            'latency_ms' => $result['latency_ms'] ?? 0,
            'error_code' => null,
        ]);
    }

    public function failure(?User $user, string $model, int $status, int $latencyMs, string $errorCode): void
    {
        $this->write([
            'user_id' => $user?->id,
            'model' => $model,
            'success' => false,
            'http_status' => $status,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => 0,
            'estimated_cost_usd' => 0,
            'latency_ms' => $latencyMs,
            'error_code' => $errorCode,
        ]);
    }

    private function write(array $attributes): void
    {
        try {
            AiRequestLog::create($attributes);
        } catch (Throwable $exception) {
            Log::warning('AI technical logging failed', [
                'error_type' => $exception::class,
            ]);
        }
    }
}
