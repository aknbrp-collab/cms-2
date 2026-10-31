<?php

namespace App\Social;

/**
 * Outcome of a publish call: done (with post id and link) or still processing.
 */
final readonly class PublishResult
{
    public function __construct(
        public string $externalId,
        public ?string $permalink = null,
        public bool $pending = false,
    ) {}

    public static function done(string $externalId, ?string $permalink = null): self
    {
        return new self($externalId, $permalink);
    }

    public static function pending(string $externalId): self
    {
        return new self($externalId, pending: true);
    }
}
