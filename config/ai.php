<?php

return [
    'enabled' => env('OPENAI_ENABLED', false),
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-5.4-nano'),
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    'timeout' => (int) env('OPENAI_TIMEOUT', 20),
    'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 300),
    'context_cache_seconds' => (int) env('OPENAI_CONTEXT_CACHE_SECONDS', 120),
    'special_days_ahead' => (int) env('OPENAI_SPECIAL_DAYS_AHEAD', 30),
    'rate_limits' => [
        'minute' => (int) env('OPENAI_RATE_LIMIT_PER_MINUTE', 3),
        'hour' => (int) env('OPENAI_RATE_LIMIT_PER_HOUR', 30),
        'day' => (int) env('OPENAI_RATE_LIMIT_PER_DAY', 100),
    ],
    'pricing' => [
        'input_per_million' => env('OPENAI_INPUT_PRICE_PER_MILLION'),
        'output_per_million' => env('OPENAI_OUTPUT_PRICE_PER_MILLION'),
    ],
];
