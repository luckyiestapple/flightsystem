<?php

namespace App\Libraries;

use RuntimeException;
use Throwable;

final class ExternalApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
