<?php

namespace App\Exceptions;

use RuntimeException;

class AiServiceException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $httpStatus,
        string $message,
        public readonly ?int $upstreamStatus = null,
        public readonly int $latencyMs = 0,
    ) {
        parent::__construct($message);
    }
}
