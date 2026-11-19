<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Fonnte (https://fonnte.com), a WhatsApp gateway common in Indonesia.
 * API: POST https://api.fonnte.com/send with the device token in the
 * Authorization header; answers {"status": true|false, "reason": "..."}.
 */
class FonnteGateway implements Gateway
{
    public const ENDPOINT = 'https://api.fonnte.com/send';

    public function __construct(protected string $token) {}

    public function send(string $phone, string $message): GatewayResult
    {
        try {
            $response = Http::withHeaders(['Authorization' => $this->token])
                ->asForm()
                ->timeout(20)
                ->post(self::ENDPOINT, ['target' => $phone, 'message' => $message, 'countryCode' => '62']);
        } catch (ConnectionException $exception) {
            return GatewayResult::failed('Connection failed: '.Str::limit($exception->getMessage(), 150), retry: true);
        }

        if ($response->serverError() || $response->status() === 429) {
            return GatewayResult::failed('Gateway error (HTTP '.$response->status().')', retry: true);
        }

        if ($response->json('status') === true) {
            return GatewayResult::sent();
        }

        $reason = $response->json('reason') ?? $response->json('detail') ?? 'HTTP '.$response->status();

        return GatewayResult::failed(Str::limit('Fonnte: '.(is_string($reason) ? $reason : json_encode($reason)), 250));
    }
}
