<?php

namespace App\Siakad;

use App\Enums\LegalQuestionStatus;
use App\Jobs\ForwardLegalQuestion;
use App\Models\LegalQuestion;
use App\Models\Site;
use App\Models\User;
use App\Notifications\LegalQuestionUpdated;
use App\Support\Audit;
use App\Support\RichText;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tanya Hukum workflow:
 *   submitted ─moderate→ rejected
 *   submitted ─forward→ forwarding ─(job)→ forwarded ─(SIAKAD webhook)→ answered ─publish→ published
 *
 * An answer SIAKAD sends after publication is held as a pending correction;
 * the published wording only changes when a moderator applies it.
 */
class TanyaHukum
{
    public function reject(LegalQuestion $question, User $actor, string $note): void
    {
        $this->authorize($actor, 'legal_questions.moderate', $question);
        $this->expect($question, LegalQuestionStatus::Submitted);

        $question->forceFill(['status' => LegalQuestionStatus::Rejected, 'moderation_note' => $note, 'moderated_by' => $actor->id])->save();
        Audit::record('legal_questions.rejected', $question, meta: ['note' => $note]);
    }

    /**
     * Queue the question for SIAKAD. The job marks it forwarded on success.
     */
    public function forward(LegalQuestion $question, User $actor, ?string $note = null): void
    {
        $this->authorize($actor, 'legal_questions.forward', $question);
        $this->expect($question, LegalQuestionStatus::Submitted);

        $question->forceFill([
            'status' => LegalQuestionStatus::Forwarding,
            'moderation_note' => $note ?: $question->moderation_note,
            'moderated_by' => $actor->id,
        ])->save();

        Audit::record('legal_questions.forward_queued', $question);
        ForwardLegalQuestion::dispatch($question);
    }

    /**
     * Retry a question whose forwarding failed.
     */
    public function retryForward(LegalQuestion $question, User $actor): void
    {
        $this->authorize($actor, 'integration_logs.retry', $question);
        $this->expect($question, LegalQuestionStatus::Submitted, LegalQuestionStatus::Forwarding);

        $question->forceFill(['status' => LegalQuestionStatus::Forwarding])->save();
        ForwardLegalQuestion::dispatch($question);
    }

    /**
     * Store the answer sent by SIAKAD and notify the Tanya Hukum admins.
     *
     * @param  array{answer_html: string, answered_by: string, references?: string|null}  $data
     */
    public function receiveAnswer(LegalQuestion $question, array $data): void
    {
        if (! in_array($question->status, [LegalQuestionStatus::Forwarded, LegalQuestionStatus::Forwarding, LegalQuestionStatus::Answered, LegalQuestionStatus::Published], true)) {
            throw ValidationException::withMessages(['question_id' => 'The question was not forwarded to SIAKAD.']);
        }

        if ($question->status === LegalQuestionStatus::Published) {
            $question->forceFill([
                'pending_answer' => RichText::clean($data['answer_html']),
                'pending_answered_by' => $data['answered_by'],
                'pending_answer_references' => $data['references'] ?? null,
                'pending_answer_at' => now(),
            ])->save();

            Notification::send($this->admins($question), new LegalQuestionUpdated($question, 'corrected'));

            return;
        }

        $question->forceFill([
            'answer' => RichText::clean($data['answer_html']),
            'answered_by' => $data['answered_by'],
            'answer_references' => $data['references'] ?? null,
            'answered_at' => now(),
            'status' => LegalQuestionStatus::Answered,
        ])->save();

        Notification::send($this->admins($question), new LegalQuestionUpdated($question, 'answered'));
    }

    /**
     * Replace the published answer with the correction SIAKAD sent.
     */
    public function applyCorrection(LegalQuestion $question, User $actor): void
    {
        $this->authorize($actor, 'legal_questions.publish', $question);
        $this->expectCorrection($question);

        $question->forceFill([
            'answer' => $question->pending_answer,
            'answered_by' => $question->pending_answered_by,
            'answer_references' => $question->pending_answer_references,
            'answered_at' => $question->pending_answer_at,
        ])->forceFill($this->noCorrection())->save();

        Audit::record('legal_questions.correction_applied', $question);
    }

    /**
     * Keep the published answer and drop the correction.
     */
    public function dismissCorrection(LegalQuestion $question, User $actor): void
    {
        $this->authorize($actor, 'legal_questions.publish', $question);
        $this->expectCorrection($question);

        $question->forceFill($this->noCorrection())->save();

        Audit::record('legal_questions.correction_dismissed', $question);
    }

    /**
     * Publish the question and answer in the public archive.
     */
    public function publish(LegalQuestion $question, User $actor, string $title, string $publicQuestion, ?string $answer = null): void
    {
        $this->authorize($actor, 'legal_questions.publish', $question);
        $this->expect($question, LegalQuestionStatus::Answered, LegalQuestionStatus::Published);

        $question->forceFill([
            'title' => $title,
            'slug' => $question->slug ?? $this->uniqueSlug($question, $title),
            'public_question' => $publicQuestion,
            'answer' => $answer !== null ? RichText::clean($answer) : $question->answer,
            'status' => LegalQuestionStatus::Published,
            'published_at' => $question->published_at ?? now(),
        ])->save();

        Audit::record('legal_questions.published', $question);
    }

    public function unpublish(LegalQuestion $question, User $actor): void
    {
        $this->authorize($actor, 'legal_questions.publish', $question);
        $this->expect($question, LegalQuestionStatus::Published);

        $question->forceFill(['status' => LegalQuestionStatus::Answered, 'published_at' => null])->save();
        Audit::record('legal_questions.unpublished', $question);
    }

    /**
     * Tell moderators about a new question from the website.
     */
    public function notifyNew(LegalQuestion $question): void
    {
        Notification::send($this->admins($question), new LegalQuestionUpdated($question, 'submitted'));
    }

    protected function authorize(User $actor, string $permission, LegalQuestion $question): void
    {
        $site = Site::query()->find($question->site_id);

        if (! $site instanceof Site || ! $actor->canAccessSite($site) || ! $actor->can($permission)) {
            throw new AuthorizationException(__('You are not allowed to do this.'));
        }
    }

    protected function expect(LegalQuestion $question, LegalQuestionStatus ...$allowed): void
    {
        if (! in_array($question->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => __('This action is not available while the question is :status.', ['status' => strtolower($question->status->label())])]);
        }
    }

    protected function expectCorrection(LegalQuestion $question): void
    {
        if ($question->pending_answer === null) {
            throw ValidationException::withMessages(['status' => __('There is no correction from SIAKAD to review.')]);
        }
    }

    /**
     * @return array<string, null>
     */
    protected function noCorrection(): array
    {
        return ['pending_answer' => null, 'pending_answered_by' => null, 'pending_answer_references' => null, 'pending_answer_at' => null];
    }

    protected function uniqueSlug(LegalQuestion $question, string $title): string
    {
        $base = Str::limit(Str::slug($title), 200, '') ?: 'pertanyaan';
        $slug = $base;
        $suffix = 2;

        while (LegalQuestion::query()->withoutGlobalScopes()->where('site_id', $question->site_id)->where('slug', $slug)->whereKeyNot($question->id)->exists()) {
            $slug = "{$base}-".$suffix++;
        }

        return $slug;
    }

    /**
     * @return Collection<int, User>
     */
    protected function admins(LegalQuestion $question): Collection
    {
        $site = Site::query()->findOrFail($question->site_id);

        return User::permission('legal_questions.moderate')
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->canAccessSite($site))
            ->values();
    }
}
