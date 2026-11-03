<?php

namespace App\Console\Commands;

use App\Enums\LegalQuestionStatus;
use App\Models\LegalQuestion;
use App\Siakad\Signature;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature as CommandSignature;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pretend to be SIAKAD: send correctly signed webhooks to this CMS so the
 * whole flow can be tried before SIAKAD exists. Requests are handled in
 * process, exactly like real HTTP calls (signature check included).
 */
#[CommandSignature('siakad:simulate {action : article|status|withdraw|answer} {--id= : SIAKAD article id or CMS question id/tracking code}')]
#[Description('Send a signed test webhook as if it came from SIAKAD')]
class SimulateSiakad extends Command
{
    public function handle(): int
    {
        if (blank(config('siakad.webhook_secret'))) {
            $this->error('Set SIAKAD_WEBHOOK_SECRET in .env first.');

            return self::FAILURE;
        }

        [$method, $route, $payload] = match ($this->argument('action')) {
            'article' => ['POST', route('webhooks.siakad.articles'), $this->articlePayload()],
            'status' => ['GET', route('webhooks.siakad.articles.show', (string) $this->option('id')), []],
            'withdraw' => ['DELETE', route('webhooks.siakad.articles.withdraw', (string) $this->option('id')), []],
            'answer' => ['POST', route('webhooks.siakad.answers'), $this->answerPayload()],
            default => [null, null, null],
        };

        if ($method === null || $payload === null) {
            $this->error('Unknown action or nothing to answer.');

            return self::FAILURE;
        }

        $body = $payload === [] ? '' : (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $request = Request::create($route, $method, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIAKAD_TIMESTAMP' => (string) now()->getTimestamp(),
            'HTTP_X_SIAKAD_SIGNATURE' => Signature::sign($body, now()->getTimestamp(), (string) config('siakad.webhook_secret')),
        ], content: $body);

        /** @var Response $response */
        $response = app()->handle($request);

        $this->line("{$method} {$route}");
        $this->line("HTTP {$response->getStatusCode()}: {$response->getContent()}");

        return $response->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string, mixed>
     */
    protected function articlePayload(): array
    {
        $id = (string) ($this->option('id') ?: 'ART-'.Str::upper(Str::random(6)));

        return [
            'id' => $id,
            'type' => 'research',
            'title' => 'Kajian Maqashid Syariah dalam Fiqh Kontemporer ('.$id.')',
            'authors' => ['Dr. H. Mahbub Ridwan, M.Ag.', 'Ahmad Syarifuddin'],
            'year' => (int) now()->format('Y'),
            'abstract' => 'Artikel uji dari simulator SIAKAD.',
            'body_html' => '<p>Isi lengkap artikel.</p>',
            'keywords' => ['maqashid', 'fiqh'],
            'source_url' => 'https://siakad.example.test/artikel/'.$id,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function answerPayload(): ?array
    {
        $id = (string) $this->option('id');

        $question = LegalQuestion::query()->withoutGlobalScopes()
            ->when(
                $id !== '',
                fn ($query) => Str::isUuid($id) ? $query->whereKey($id) : $query->where('tracking_code', $id),
                fn ($query) => $query->where('status', LegalQuestionStatus::Forwarded->value)->oldest('forwarded_at'),
            )
            ->first();

        if ($question === null) {
            return null;
        }

        return [
            'question_id' => $question->id,
            'siakad_question_id' => $question->external_id,
            'answer_html' => '<p>Jawaban uji dari simulator SIAKAD. Wallahu a\'lam bish-shawab.</p>',
            'answered_by' => 'Dewan Asatidz Ma\'had Aly',
            'references' => "Fathul Mu'in, Bab Shalat",
        ];
    }
}
