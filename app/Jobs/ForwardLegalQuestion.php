<?php

namespace App\Jobs;

use App\Enums\LegalQuestionStatus;
use App\Models\IntegrationLog;
use App\Models\LegalQuestion;
use App\Siakad\IntegrationLogger;
use App\Siakad\SiakadClient;
use App\Siakad\SiakadException;
use App\Support\Audit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Send a moderated question to SIAKAD, retrying with backoff on failure.
 */
class ForwardLegalQuestion implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public LegalQuestion $question) {}

    public function handle(SiakadClient $client): void
    {
        $question = $this->question;

        if ($question->status !== LegalQuestionStatus::Forwarding) {
            return;
        }

        $started = microtime(true);

        try {
            $result = $client->forwardQuestion($question);
        } catch (SiakadException $e) {
            IntegrationLogger::log(
                IntegrationLog::OUTBOUND, 'legal_questions.forward', IntegrationLog::FAILED, $question,
                request: $client->questionPayload($question), response: $e->response, httpStatus: $e->httpStatus,
                error: $e->getMessage(), durationMs: $this->elapsed($started),
            );

            // A refused request fails the same way every time: hand it back now.
            if ($e->isPermanent()) {
                $this->fail($e);

                return;
            }

            throw $e;
        }

        IntegrationLogger::log(
            IntegrationLog::OUTBOUND, 'legal_questions.forward', IntegrationLog::SUCCESS, $question,
            externalId: $result['id'], request: $client->questionPayload($question), response: $result['response'],
            httpStatus: $result['http_status'], durationMs: $this->elapsed($started),
        );

        $question->forceFill([
            'status' => LegalQuestionStatus::Forwarded,
            'external_id' => $result['id'],
            'forwarded_at' => now(),
        ])->save();
    }

    /**
     * All retries failed: give the question back to the moderator.
     */
    public function failed(?Throwable $exception): void
    {
        $this->question->forceFill(['status' => LegalQuestionStatus::Submitted])->save();

        Audit::record('legal_questions.forward_failed', $this->question, meta: ['error' => $exception?->getMessage()]);
    }

    protected function elapsed(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
