<?php

namespace App\Services\WhatsApp;

/**
 * Outcome of a gateway send. "retry" marks failures worth trying again
 * (network, rate limits) as opposed to a rejected number or a bad key.
 */
final readonly class GatewayResult
{
    public function __construct(
        public bool $ok,
        public ?string $error = null,
        public bool $retry = false,
    ) {}

    public static function sent(): self
    {
        return new self(true);
    }

    public static function failed(string $error, bool $retry = false): self
    {
        return new self(false, $error, $retry);
    }
}
