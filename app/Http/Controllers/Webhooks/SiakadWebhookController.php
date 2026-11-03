<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\ContentStatus;
use App\Enums\PublicationType;
use App\Enums\SiakadArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\IntegrationLog;
use App\Models\LegalQuestion;
use App\Models\SiakadArticle;
use App\Siakad\ArticleInbox;
use App\Siakad\IntegrationLogger;
use App\Siakad\TanyaHukum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Signed webhooks from SIAKAD (see docs/siakad-api-spec.md). Every call is
 * written to the integration log, including refused ones.
 */
class SiakadWebhookController extends Controller
{
    /**
     * POST /api/webhooks/siakad/articles: create or update an article.
     */
    public function article(Request $request, ArticleInbox $inbox): JsonResponse
    {
        $data = $this->validated($request, 'articles.received', [
            'id' => ['required', 'string', 'max:191'],
            'type' => ['nullable', Rule::enum(PublicationType::class)],
            'title' => ['required', 'string', 'max:255'],
            'authors' => ['required'],
            'authors.*' => ['string', 'max:255'],
            'year' => ['required', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'abstract' => ['nullable', 'string', 'max:20000'],
            'body_html' => ['nullable', 'string', 'max:1000000'],
            'keywords' => ['nullable'],
            'keywords.*' => ['string', 'max:100'],
            'doi' => ['nullable', 'string', 'max:255'],
            'file_url' => ['nullable', 'url', 'max:2048'],
            'source_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $existing = SiakadArticle::query()->withoutGlobalScopes()->where('external_id', $data['id'])->exists();
        $article = $inbox->receive($data);

        $body = ['status' => 'received', 'cms_id' => $article->id, 'review_status' => $article->status->value];
        $this->logSuccess($request, 'articles.received', $article, $data['id'], $body);

        return response()->json($body, $existing ? 200 : 201);
    }

    /**
     * GET /api/webhooks/siakad/articles/{externalId}: review status and public
     * URL, so SIAKAD can show where an article stands. Not written to the
     * integration log (SIAKAD may poll it).
     */
    public function showArticle(string $externalId): JsonResponse
    {
        $article = SiakadArticle::query()->withoutGlobalScopes()->with('publication.site')->where('external_id', $externalId)->first();

        if ($article === null) {
            return response()->json(['message' => 'Article not found.'], 404);
        }

        $publication = $article->publication;
        $isPublic = $publication !== null && $publication->status === ContentStatus::Published
            && $publication->published_at?->isPast() && ! $publication->is_hidden;

        return response()->json([
            'id' => $article->external_id,
            'cms_id' => $article->id,
            'review_status' => $article->status->value,
            'review_note' => $article->status === SiakadArticleStatus::Rejected ? $article->review_note : null,
            'reviewed_at' => $article->reviewed_at?->toIso8601String(),
            'changes_pending_review' => $article->updated_after_approval && $article->status === SiakadArticleStatus::UnderReview,
            'public_url' => $isPublic ? $publication->site->url('publikasi/'.$publication->slug) : null,
            'received_at' => $article->received_at->toIso8601String(),
        ]);
    }

    /**
     * DELETE /api/webhooks/siakad/articles/{externalId}: withdraw an article.
     */
    public function withdrawArticle(Request $request, string $externalId, ArticleInbox $inbox): JsonResponse
    {
        $article = SiakadArticle::query()->withoutGlobalScopes()->where('external_id', $externalId)->first();

        if ($article === null) {
            IntegrationLogger::log(IntegrationLog::INBOUND, 'articles.withdrawn', IntegrationLog::REJECTED, externalId: $externalId, httpStatus: 404, error: 'Unknown article.');

            return response()->json(['message' => 'Article not found.'], 404);
        }

        $inbox->withdraw($article);

        $body = ['status' => 'withdrawn', 'cms_id' => $article->id];
        $this->logSuccess($request, 'articles.withdrawn', $article, $externalId, $body);

        return response()->json($body);
    }

    /**
     * POST /api/webhooks/siakad/answers: the answer to a forwarded question.
     */
    public function answer(Request $request, TanyaHukum $tanyaHukum): JsonResponse
    {
        $data = $this->validated($request, 'legal_questions.answered', [
            'question_id' => ['required_without:siakad_question_id', 'nullable', 'string', 'max:191'],
            'siakad_question_id' => ['required_without:question_id', 'nullable', 'string', 'max:191'],
            'answer_html' => ['required', 'string', 'max:200000'],
            'answered_by' => ['required', 'string', 'max:255'],
            'references' => ['nullable', 'string', 'max:5000'],
        ]);

        $question = LegalQuestion::query()->withoutGlobalScopes()
            ->when(
                filled($data['question_id'] ?? null) && Str::isUuid($data['question_id']),
                fn ($query) => $query->whereKey($data['question_id']),
                fn ($query) => $query->where('external_id', $data['siakad_question_id'] ?? ''),
            )
            ->first();

        if ($question === null) {
            IntegrationLogger::log(IntegrationLog::INBOUND, 'legal_questions.answered', IntegrationLog::REJECTED, request: $data, httpStatus: 404, error: 'Unknown question.');

            return response()->json(['message' => 'Question not found.'], 404);
        }

        try {
            $tanyaHukum->receiveAnswer($question, [
                'answer_html' => (string) $data['answer_html'],
                'answered_by' => (string) $data['answered_by'],
                'references' => isset($data['references']) ? (string) $data['references'] : null,
            ]);
        } catch (ValidationException $e) {
            IntegrationLogger::log(IntegrationLog::INBOUND, 'legal_questions.answered', IntegrationLog::REJECTED, $question, request: $data, httpStatus: 409, error: $e->getMessage());

            return response()->json(['message' => $e->getMessage()], 409);
        }

        $body = [
            'status' => 'received',
            'cms_id' => $question->id,
            'question_status' => $question->status->value,
            // After publication the answer waits for a moderator before it replaces the published one.
            'correction_pending' => $question->pending_answer !== null,
        ];
        $this->logSuccess($request, 'legal_questions.answered', $question, $question->external_id, $body);

        return response()->json($body);
    }

    /**
     * Validate the JSON body, logging and rejecting invalid payloads.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function validated(Request $request, string $action, array $rules): array
    {
        $validator = Validator::make($request->json()->all(), $rules);

        if ($validator->fails()) {
            IntegrationLogger::log(
                IntegrationLog::INBOUND, $action, IntegrationLog::REJECTED,
                request: $this->redact($request->json()->all()), httpStatus: 422, error: $validator->errors()->toJson(),
            );

            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function logSuccess(Request $request, string $action, Model $subject, ?string $externalId, array $response): void
    {
        IntegrationLogger::log(
            IntegrationLog::INBOUND, $action, IntegrationLog::SUCCESS, $subject,
            externalId: $externalId, request: $this->redact($request->json()->all()), response: $response, httpStatus: 200,
        );
    }

    /**
     * Keep big HTML bodies out of the log.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function redact(array $payload): array
    {
        foreach (['body_html', 'answer_html'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key])) {
                $payload[$key] = Str::limit(strip_tags($payload[$key]), 200);
            }
        }

        return $payload;
    }
}
