<?php

namespace App\Siakad;

/**
 * HMAC request signing shared by the CMS and SIAKAD.
 *
 * signature = hex(HMAC-SHA256(secret, "{timestamp}.{raw body}"))
 * sent as headers X-Siakad-Timestamp (unix seconds) and X-Siakad-Signature.
 */
class Signature
{
    public const TIMESTAMP_HEADER = 'X-Siakad-Timestamp';

    public const SIGNATURE_HEADER = 'X-Siakad-Signature';

    public static function sign(string $body, int $timestamp, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /**
     * @return array{X-Siakad-Timestamp: string, X-Siakad-Signature: string}
     */
    public static function headers(string $body, string $secret, ?int $timestamp = null): array
    {
        $timestamp ??= now()->getTimestamp();

        return [
            self::TIMESTAMP_HEADER => (string) $timestamp,
            self::SIGNATURE_HEADER => self::sign($body, $timestamp, $secret),
        ];
    }

    public static function verify(string $body, string $timestamp, string $signature, string $secret, int $tolerance): bool
    {
        if (! ctype_digit($timestamp) || abs(now()->getTimestamp() - (int) $timestamp) > $tolerance) {
            return false;
        }

        return hash_equals(self::sign($body, (int) $timestamp, $secret), strtolower($signature));
    }
}
