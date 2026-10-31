<?php

namespace App\Siakad;

use App\Models\LegalQuestion;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * SIAKAD client over HTTPS: Bearer API key plus an HMAC signature of the body.
 */
class HttpSiakadClient implements SiakadClient
{
    public function forwardQuestion(LegalQuestion $question): array
    {
        $body = (string) json_encode($this->questionPayload($question), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $response = Http::withToken((string) config('siakad.api_key'))
                ->withHeaders([
                    ...Signature::headers($body, (string) config('siakad.webhook_secret')),
                    'Idempotency-Key' => $question->id,
                ])
                ->timeout((int) config('siakad.timeout'))
                ->acceptJson()
                ->withBody($body, 'application/json')
                ->post(config('siakad.base_url').'/legal-questions');
        } catch (ConnectionException $e) {
            throw new SiakadException('Could not reach SIAKAD: '.$e->getMessage());
        }

        $json = (array) $response->json();

        if ($response->failed()) {
            throw new SiakadException("SIAKAD responded with HTTP {$response->status()}.", $response->status(), $json);
        }

        if (! is_string($json['id'] ?? null) || $json['id'] === '') {
            throw new SiakadException('SIAKAD response has no "id".', $response->status(), $json);
        }

        return ['id' => $json['id'], 'response' => $json, 'http_status' => $response->status()];
    }

    public function questionPayload(LegalQuestion $question): array
    {
        return [
            'cms_question_id' => $question->id,
            'tracking_code' => $question->tracking_code,
            'category' => $question->category->value,
            'question' => $question->question,
            'asker' => [
                'name' => $question->is_anonymous ? null : $question->asker_name,
                'city' => $question->asker_city,
                'anonymous' => $question->is_anonymous,
            ],
            'submitted_at' => $question->created_at?->toIso8601String(),
            'moderator_note' => $question->moderation_note,
            'callback_url' => route('webhooks.siakad.answers'),
        ];
    }
}
