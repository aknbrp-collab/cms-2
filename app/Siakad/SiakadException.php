<?php

namespace App\Siakad;

use RuntimeException;

/**
 * A call to SIAKAD failed (network error, non-2xx response or bad response body).
 */
class SiakadException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $response
     */
    public function __construct(string $message, public readonly ?int $httpStatus = null, public readonly ?array $response = null)
    {
        parent::__construct($message);
    }
}
