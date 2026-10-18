<?php

namespace App\Services\Otp;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class OtpThrottledException extends RuntimeException
{
    public function __construct(string $message, public readonly int $retryAfter)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(
            ['message' => $this->getMessage(), 'retry_after' => $this->retryAfter],
            429,
            ['Retry-After' => $this->retryAfter],
        );
    }
}
