<?php

namespace App\Exceptions;

use RuntimeException;

class PayPalException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $debugId = null,
        public readonly int $httpStatus = 0,
    ) {
        parent::__construct($message);
    }
}
