<?php

namespace App\Siakad;

use App\Enums\ContentStatus;
use App\Enums\PublicationType;
use App\Enums\SiakadArticleStatus;
use App\Enums\SiteKey;
use App\Models\Publication;
use App\Models\SiakadArticle;
use App\Models\Site;
use App\Models\User;
use App\Notifications\SiakadArticleReceived;
use App\Services\ContentWorkflow;
use App\Support\Audit;
use App\Support\RichText;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Articles pushed by SIAKAD: Draft → Under Review → Approved for website
 * publication (becomes a published Publication), or Rejected.
 */
class ArticleInbox
{
    public function __construct(protected ContentWorkflow $workflow) {}

    /**
     * Create or update an article from a webhook payload (idempotent on external_id).
     *
     * @param  array<string, mixed>  $data  Validated payload.
     */
    public function receive(array $data): SiakadArticle
    {
        $site = Site::findByKey(SiteKey::from((string) config('siakad.site'))) ?? abort(500, 'SIAKAD site is not configured.');

        $article = SiakadArticle::query()->withoutGlobalScopes()->firstOrNew(['external_id' => $data['id']]);
        $isNew = ! $article->exists;

        $article->fill([
            'site_id' => $site->id,
            'type' => $data['type'] ?? PublicationType::Research->value,
            'title' => $data['title'],
            'authors' => implode(', ', (array) $data['authors']),
            'abstract' => $data['abstract'] ?? null,
            'body' => isset($data['body_html']) ? RichText::clean($data['body_html']) : null,
            'year' => $data['year'],
            'keywords' => isset($data['keywords']) ? implode(', ', (array) $data['keywords']) : null,
            'doi' => $data['doi'] ?? null,
            'file_url' => $data['file_url'] ?? null,
            'source_url' => $data['source_url'] ?? null,
            'payload' => $data,
            'received_at' => now(),
        ]);

        // Changes after approval need a fresh look before they reach the website;
        // a re-sent rejected article is the author's fix and goes back to review.
        $resubmitted = ! $isNew && $article->status === SiakadArticleStatus::Rejected;

        if (! $isNew && in_array($article->status, [SiakadArticleStatus::Approved, SiakadArticleStatus::Withdrawn, SiakadArticleStatus::Rejected], true)) {
            $article->forceFill(['status' => SiakadArticleStatus::UnderReview, 'updated_after_approval' => $article->status === SiakadArticleStatus::Approved, 'withdrawn_at' => null]);
        }

        $article->save();

        if ($isNew || $resubmitted) {
            Notification::send($this->reviewers($site), new SiakadArticleReceived($article, $resubmitted));
        }

        return $article;
    }

    public function startReview(SiakadArticle $article, User $actor): void
    {
        $this->authorize($actor, 'siakad_articles.review', $article);
        $this->expect($article, SiakadArticleStatus::Draft, SiakadArticleStatus::Rejected);

        $this->transition($article, $actor, SiakadArticleStatus::UnderReview, 'review_started');
    }

    /**
     * Approve for the website: create or update the Publication and publish it.
     */
    public function approve(SiakadArticle $article, User $actor): Publication
    {
        $this->authorize($actor, 'siakad_articles.publish', $article);
        $this->expect($article, SiakadArticleStatus::Draft, SiakadArticleStatus::UnderReview);

        $publication = $article->publication()->withTrashed()->first() ?? new Publication([
            'site_id' => $article->site_id,
            'external_source' => 'siakad',
            'external_id' => $article->external_id,
        ]);

        if ($publication->trashed()) {
            $publication->restore();
        }

        $publication->fill([
            'type' => $article->type,
            'title' => $article->title,
            'slug' => $publication->slug ?? $this->uniqueSlug($article),
            'authors' => $article->authors,
            'abstract' => $article->abstract,
            'body' => $article->body,
            'year' => $article->year,
            'keywords' => $article->keywords,
            'doi' => $article->doi,
            'external_url' => $article->source_url ?? $article->file_url,
        ])->save();

        if ($publication->status !== ContentStatus::Published) {
            if ($publication->status === ContentStatus::Archived) {
                $this->workflow->restore($publication, $actor);
            }

            $this->workflow->publish($publication->refresh(), $actor);
        }

        $article->forceFill(['publication_id' => $publication->id, 'updated_after_approval' => false])->save();
        $this->transition($article, $actor, SiakadArticleStatus::Approved, 'approved');

        return $publication;
    }

    public function reject(SiakadArticle $article, User $actor, string $note): void
    {
        $this->authorize($actor, 'siakad_articles.publish', $article);
        $this->expect($article, SiakadArticleStatus::Draft, SiakadArticleStatus::UnderReview);

        $this->transition($article, $actor, SiakadArticleStatus::Rejected, 'rejected', $note);
    }

    /**
     * SIAKAD withdrew the article: take it off the website.
     */
    public function withdraw(SiakadArticle $article): void
    {
        $publication = $article->publication;

        if ($publication && in_array($publication->status, [ContentStatus::Published, ContentStatus::Approved], true)) {
            $publication->forceFill(['status' => ContentStatus::Archived, 'publish_at' => null])->saveQuietly();
            Audit::record('workflow.archived', $publication, meta: ['by' => 'siakad', 'reason' => 'withdrawn']);
        }

        $article->forceFill(['status' => SiakadArticleStatus::Withdrawn, 'withdrawn_at' => now()])->save();
    }

    protected function transition(SiakadArticle $article, User $actor, SiakadArticleStatus $to, string $event, ?string $note = null): void
    {
        $article->forceFill([
            'status' => $to,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_note' => $note ?? $article->review_note,
        ])->saveQuietly();

        Audit::record("siakad_articles.{$event}", $article, meta: ['to' => $to->value, 'note' => $note]);
    }

    protected function authorize(User $actor, string $permission, SiakadArticle $article): void
    {
        $site = $article->site ?? Site::query()->find($article->site_id);

        if (! $site instanceof Site || ! $actor->canAccessSite($site) || ! $actor->can($permission)) {
            throw new AuthorizationException(__('You are not allowed to do this.'));
        }
    }

    protected function expect(SiakadArticle $article, SiakadArticleStatus ...$allowed): void
    {
        if (! in_array($article->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => __('This action is not available while the article is :status.', ['status' => strtolower($article->status->label())])]);
        }
    }

    protected function uniqueSlug(SiakadArticle $article): string
    {
        $base = Str::limit(Str::slug($article->title), 200, '') ?: 'artikel';
        $slug = $base;
        $suffix = 2;

        while (Publication::withTrashed()->withoutGlobalScopes()->where('site_id', $article->site_id)->where('slug', $slug)->exists()) {
            $slug = "{$base}-".$suffix++;
        }

        return $slug;
    }

    /**
     * @return Collection<int, User>
     */
    protected function reviewers(Site $site): Collection
    {
        return User::permission('siakad_articles.review')
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->canAccessSite($site))
            ->values();
    }
}
