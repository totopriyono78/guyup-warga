<?php

namespace App\Services;

use RuntimeException;

class AinoException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $responseCode = null, public readonly array $response = [])
    {
        parent::__construct($message);
    }
}
