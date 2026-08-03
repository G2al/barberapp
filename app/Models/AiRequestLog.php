<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiRequestLog extends Model
{
    protected $fillable = [
        'user_id',
        'model',
        'success',
        'http_status',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'estimated_cost_usd',
        'latency_ms',
        'error_code',
    ];

    protected $casts = [
        'success' => 'boolean',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'total_tokens' => 'integer',
        'estimated_cost_usd' => 'decimal:8',
        'latency_ms' => 'integer',
        'http_status' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
