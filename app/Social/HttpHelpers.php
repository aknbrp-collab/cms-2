<?php

namespace App\Social;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Shared HTTP handling for the platform drivers.
 */
trait HttpHelpers
{
    protected function http(): PendingRequest
    {
        return Http::acceptJson()->timeout((int) config('social.timeout'));
    }

    /**
     * Run a request and turn failures into SocialException.
     *
     * @param  callable(PendingRequest): Response  $send
     * @return array<string, mixed>
     */
    protected function call(callable $send, string $platform): array
    {
        try {
            $response = $send($this->http());
        } catch (ConnectionException $e) {
            throw new SocialException("{$platform}: {$e->getMessage()}", retryable: true);
        }

        $json = (array) $response->json();

        if ($response->failed() || $this->hasApiError($json)) {
            throw new SocialException(
                "{$platform}: ".$this->errorMessage($json, $response),
                retryable: $response->serverError() || $response->status() === 429,
                tokenInvalid: $response->status() === 401 || $this->isTokenError($json),
            );
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function hasApiError(array $json): bool
    {
        // TikTok returns {"error": {"code": "ok"}} on success.
        return isset($json['error']) && (! is_array($json['error']) || ($json['error']['code'] ?? 'ok') !== 'ok');
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function errorMessage(array $json, Response $response): string
    {
        $error = $json['error'] ?? null;

        if (is_array($error)) {
            return (string) ($error['message'] ?? $error['code'] ?? "HTTP {$response->status()}");
        }

        return (string) ($json['error_description'] ?? $error ?? "HTTP {$response->status()}");
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function isTokenError(array $json): bool
    {
        $error = $json['error'] ?? null;

        // Meta: OAuthException code 190; TikTok: access_token_invalid.
        return is_array($error) && (($error['code'] ?? null) === 190 || ($error['code'] ?? null) === 'access_token_invalid');
    }
}
