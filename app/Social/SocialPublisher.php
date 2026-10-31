<?php

namespace App\Social;

use App\Enums\SocialPostStatus;
use App\Enums\SocialProvider;
use App\Enums\SocialTargetStatus;
use App\Jobs\PublishSocialTarget;
use App\Models\MediaAsset;
use App\Models\Site;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Models\User;
use App\Support\Audit;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Schedule, publish and retry social posts. Every target becomes its own
 * queued job (see PublishSocialTarget).
 */
class SocialPublisher
{
    /**
     * Check every target against its platform's rules.
     *
     * @return array<string, string> Error messages keyed by target id (or "post").
     */
    public function problems(SocialPost $post): array
    {
        $problems = [];
        $media = $post->mediaAssets();
        $hasVideo = $media->contains(fn (MediaAsset $asset) => str_starts_with((string) $asset->file()?->mime_type, 'video/'));
        $targets = $post->targets()->with('account')->get();

        if ($targets->isEmpty()) {
            $problems['post'] = __('Choose at least one account.');
        }

        foreach ($targets as $target) {
            $target->setRelation('post', $post);
            $provider = $target->account->provider;
            $caption = $target->finalCaption();
            $hashtags = substr_count($caption, '#');
            $name = $target->account->displayName();

            $problems[$target->id] = match (true) {
                ! $target->account->isUsable() => __(':account must be reconnected.', ['account' => $name]),
                mb_strlen($caption) > $provider->captionLimit() => __(':account: caption is :count characters, the limit is :limit.', ['account' => $name, 'count' => mb_strlen($caption), 'limit' => $provider->captionLimit()]),
                $provider->hashtagLimit() !== null && $hashtags > $provider->hashtagLimit() => __(':account: at most :limit hashtags.', ['account' => $name, 'limit' => $provider->hashtagLimit()]),
                $provider->requiresMedia() && $media->isEmpty() => __(':account needs at least one image or video.', ['account' => $name]),
                $provider === SocialProvider::TikTok && $hasVideo && $media->count() > 1 => __(':account: post one video, or photos only.', ['account' => $name]),
                $provider === SocialProvider::TikTok && empty($target->options['privacy_level']) => __(':account: choose who can view this post.', ['account' => $name]),
                $provider === SocialProvider::TikTok && ! empty($target->options['brand_content']) && ($target->options['privacy_level'] ?? '') === 'SELF_ONLY' => __(':account: branded content cannot be private.', ['account' => $name]),
                default => '',
            };
        }

        return array_filter($problems);
    }

    /**
     * Schedule the post, or publish it right away when $at is null or past.
     */
    public function schedule(SocialPost $post, User $actor, ?CarbonInterface $at = null): void
    {
        $this->authorize($actor, $post);
        $this->ensureEditable($post);

        if ($problems = $this->problems($post)) {
            throw ValidationException::withMessages(['targets' => array_values($problems)]);
        }

        if ($at !== null && $at->isFuture()) {
            $post->forceFill(['status' => SocialPostStatus::Scheduled, 'scheduled_at' => $at])->save();
            Audit::record('social.scheduled', $post, meta: ['at' => $at->toIso8601String()]);

            return;
        }

        $post->forceFill(['scheduled_at' => now()])->save();
        $this->dispatch($post);
    }

    /**
     * Move a scheduled post to another date (calendar drag and drop).
     */
    public function reschedule(SocialPost $post, User $actor, CarbonInterface $at): void
    {
        $this->authorize($actor, $post);
        $this->ensureEditable($post);

        if (! $at->isFuture()) {
            throw ValidationException::withMessages(['scheduled_at' => __('Choose a time in the future.')]);
        }

        $post->forceFill(['scheduled_at' => $at, 'status' => $post->status === SocialPostStatus::Draft ? SocialPostStatus::Draft : SocialPostStatus::Scheduled])->save();
        Audit::record('social.rescheduled', $post, meta: ['at' => $at->toIso8601String()]);
    }

    public function unschedule(SocialPost $post, User $actor): void
    {
        $this->authorize($actor, $post);

        if ($post->status !== SocialPostStatus::Scheduled) {
            return;
        }

        $post->forceFill(['status' => SocialPostStatus::Draft])->save();
    }

    /**
     * Retry one failed account.
     */
    public function retry(SocialPostTarget $target, User $actor): void
    {
        $post = $target->post;
        $this->authorize($actor, $post);

        if ($target->status !== SocialTargetStatus::Failed) {
            return;
        }

        $target->forceFill(['status' => SocialTargetStatus::Pending, 'last_error' => null])->save();
        $post->forceFill(['status' => SocialPostStatus::Publishing])->save();
        PublishSocialTarget::dispatch($target);
    }

    /**
     * Start publishing scheduled posts whose time has come.
     */
    public function dispatchDue(): int
    {
        $due = SocialPost::query()->withoutGlobalScopes()
            ->where('status', SocialPostStatus::Scheduled->value)
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($due as $post) {
            $this->dispatch($post);
        }

        return $due->count();
    }

    protected function dispatch(SocialPost $post): void
    {
        $post->forceFill(['status' => SocialPostStatus::Publishing])->save();

        foreach ($post->targets()->where('status', '!=', SocialTargetStatus::Published->value)->get() as $target) {
            $target->forceFill(['status' => SocialTargetStatus::Pending, 'last_error' => null])->save();
            PublishSocialTarget::dispatch($target);
        }

        Audit::record('social.dispatched', $post);
    }

    protected function ensureEditable(SocialPost $post): void
    {
        if (! $post->status->isEditable()) {
            throw ValidationException::withMessages(['status' => __('This post is already :status.', ['status' => strtolower($post->status->label())])]);
        }
    }

    protected function authorize(User $actor, SocialPost $post): void
    {
        $site = Site::query()->find($post->site_id);

        if (! $site instanceof Site || ! $actor->canAccessSite($site) || ! $actor->can('social_posts.publish')) {
            throw new AuthorizationException(__('You are not allowed to do this.'));
        }
    }
}
