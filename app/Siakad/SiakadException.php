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
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?array $response = null,
        public readonly bool $retryable = true,
    ) {
        parent::__construct($message);
    }

    /**
     * Whether retrying cannot help: the request was refused (bad key, signature
     * or payload) or the answer can never be used (e.g. not an image).
     * Timeouts, 408, 429 and 5xx are worth retrying.
     */
    public function isPermanent(): bool
    {
        return ! $this->retryable || $this->httpStatus !== null
            && $this->httpStatus >= 400 && $this->httpStatus < 500
            && ! in_array($this->httpStatus, [408, 429], true);
    }
}
