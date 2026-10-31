<?php

namespace App\Services;

use App\Contracts\Reviewable;
use App\Enums\ContentStatus;
use App\Models\User;
use App\Notifications\ContentReviewed;
use App\Notifications\ContentSubmitted;
use App\Support\Audit;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Status transitions of the editorial workflow.
 *
 * draft ──submit──▶ under_review ──approve──▶ published (or approved until publish_at)
 *   ▲                    │
 *   └──────reject────────┘        publishers may also publish, unpublish and archive directly.
 */
class ContentWorkflow
{
    /**
     * Send a draft to the reviewers.
     */
    public function submit(Reviewable&Model $content, User $actor): void
    {
        $this->authorize($actor, 'submit', $content);
        $this->expectStatus($content, ContentStatus::Draft);

        $this->transition($content, $actor, ContentStatus::UnderReview, 'submitted', [
            'submitted_at' => now(),
            'review_note' => null,
        ]);

        Notification::send(
            $this->reviewersFor($content)->reject(fn (User $user) => $user->is($actor)),
            new ContentSubmitted($content, $actor),
        );
    }

    /**
     * Approve content under review; it is published now or at $publishAt.
     */
    public function approve(Reviewable&Model $content, User $actor, ?CarbonInterface $publishAt = null, ?string $note = null): void
    {
        $this->authorize($actor, 'publish', $content);
        $this->expectStatus($content, ContentStatus::UnderReview);

        $this->release($content, $actor, $publishAt, 'approved', [
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        $this->notifyAuthor($content, $actor, approved: true);
    }

    /**
     * Send content under review back to draft with a note for the author.
     */
    public function reject(Reviewable&Model $content, User $actor, string $note): void
    {
        $this->authorize($actor, 'publish', $content);
        $this->expectStatus($content, ContentStatus::UnderReview);

        $this->transition($content, $actor, ContentStatus::Draft, 'rejected', [
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ], ['note' => $note]);

        $this->notifyAuthor($content, $actor, approved: false);
    }

    /**
     * Publish directly (publishers may skip review), now or at $publishAt.
     */
    public function publish(Reviewable&Model $content, User $actor, ?CarbonInterface $publishAt = null): void
    {
        $this->authorize($actor, 'publish', $content);
        $this->expectStatus($content, ContentStatus::Draft, ContentStatus::UnderReview, ContentStatus::Approved);

        $this->release($content, $actor, $publishAt, 'published');
    }

    /**
     * Take published or scheduled content offline and back to draft.
     */
    public function unpublish(Reviewable&Model $content, User $actor): void
    {
        $this->authorize($actor, 'publish', $content);
        $this->expectStatus($content, ContentStatus::Published, ContentStatus::Approved);

        $this->transition($content, $actor, ContentStatus::Draft, 'unpublished', [
            'publish_at' => null,
            'published_at' => null,
        ]);
    }

    public function archive(Reviewable&Model $content, User $actor): void
    {
        $this->authorize($actor, 'publish', $content);
        $this->expectStatus($content, ContentStatus::Draft, ContentStatus::Approved, ContentStatus::Published);

        $this->transition($content, $actor, ContentStatus::Archived, 'archived', ['publish_at' => null]);
    }

    public function restore(Reviewable&Model $content, User $actor): void
    {
        $this->authorize($actor, 'publish', $content);
        $this->expectStatus($content, ContentStatus::Archived);

        $this->transition($content, $actor, ContentStatus::Draft, 'restored', ['published_at' => null]);
    }

    /**
     * Publish approved content whose publish date has arrived.
     *
     * @param  class-string<Reviewable&Model>  $modelClass
     */
    public function releaseScheduled(string $modelClass): int
    {
        $due = $modelClass::query()
            ->withoutGlobalScope('site')
            ->where('status', ContentStatus::Approved->value)
            ->where('publish_at', '<=', now())
            ->get();

        foreach ($due as $content) {
            $this->transition($content, null, ContentStatus::Published, 'scheduled_release', [
                'published_at' => $content->getAttribute('publish_at'),
            ]);
        }

        return $due->count();
    }

    /**
     * Move to published now, or to approved until a future publish date.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function release(Reviewable&Model $content, User $actor, ?CarbonInterface $publishAt, string $event, array $attributes = []): void
    {
        $scheduled = $publishAt !== null && $publishAt->isFuture();

        $this->transition(
            $content,
            $actor,
            $scheduled ? ContentStatus::Approved : ContentStatus::Published,
            $scheduled ? 'scheduled' : $event,
            [
                ...$attributes,
                'publish_at' => $publishAt,
                'published_at' => $scheduled ? null : ($content->getAttribute('published_at') ?? now()),
            ],
            $scheduled ? ['publish_at' => $publishAt->toIso8601String()] : [],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $meta
     */
    protected function transition(Reviewable&Model $content, ?User $actor, ContentStatus $to, string $event, array $attributes = [], array $meta = []): void
    {
        $from = $content->getAttribute('status');

        $content->forceFill([...$attributes, 'status' => $to])->saveQuietly();

        Audit::record("workflow.{$event}", $content, meta: [
            ...$meta,
            'from' => $from instanceof ContentStatus ? $from->value : $from,
            'to' => $to->value,
            'by' => $actor?->id,
        ]);
    }

    protected function authorize(User $actor, string $ability, Model $content): void
    {
        if (Gate::forUser($actor)->denies($ability, $content)) {
            throw new AuthorizationException(__('You are not allowed to do this.'));
        }
    }

    protected function expectStatus(Model $content, ContentStatus ...$allowed): void
    {
        if (! in_array($content->getAttribute('status'), $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __('This action is not available while the content is :status.', [
                    'status' => strtolower($content->getAttribute('status')->label()),
                ]),
            ]);
        }
    }

    /**
     * Users who may publish the given content.
     *
     * @return Collection<int, User>
     */
    protected function reviewersFor(Reviewable&Model $content): Collection
    {
        return User::permission($content::permissionModule().'.publish')
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => Gate::forUser($user)->allows('publish', $content))
            ->values();
    }

    protected function notifyAuthor(Reviewable&Model $content, User $actor, bool $approved): void
    {
        $author = $content->getRelationValue('author');

        if ($author instanceof User && ! $author->is($actor)) {
            $author->notify(new ContentReviewed($content, $actor, $approved));
        }
    }
}
