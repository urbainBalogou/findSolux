<?php

namespace App\Services\Payments;

use RuntimeException;
use Throwable;

class FedaPayException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
