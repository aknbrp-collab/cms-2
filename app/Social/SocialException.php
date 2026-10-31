<?php

namespace App\Social;

use RuntimeException;

/**
 * A social platform call failed. Retryable errors (network, rate limits,
 * 5xx) are retried by the job; others fail the target immediately.
 */
class SocialException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false, public readonly bool $tokenInvalid = false)
    {
        parent::__construct($message);
    }
}
