<?php

namespace App\Jobs;

use App\Enums\SocialTargetStatus;
use App\Models\SocialPostTarget;
use App\Social\SocialException;
use App\Social\SocialManager;
use App\Support\Audit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Poll a publish that the platform processes asynchronously (TikTok).
 */
class CheckSocialPublishStatus implements ShouldQueue
{
    use Queueable;

    public int $tries = 20;

    public function __construct(public SocialPostTarget $target) {}

    public function handle(SocialManager $social): void
    {
        $target = $this->target->fresh(['account', 'post']) ?? $this->target;

        if ($target->status !== SocialTargetStatus::Queued) {
            return;
        }

        try {
            $result = $social->forProvider($target->account->provider)->checkStatus($target);
        } catch (SocialException $e) {
            if ($e->retryable && $this->attempts() < $this->tries) {
                $this->release(60);

                return;
            }

            $target->forceFill(['status' => SocialTargetStatus::Failed, 'last_error' => $e->getMessage()])->save();
            Audit::record('social.failed', $target->post, meta: ['account' => $target->account->displayName(), 'error' => $e->getMessage()]);
            $target->post->refreshStatus();

            return;
        }

        if ($result->pending) {
            if ($this->attempts() >= $this->tries) {
                $target->forceFill(['status' => SocialTargetStatus::Failed, 'last_error' => __('The platform did not confirm the post in time.')])->save();
                $target->post->refreshStatus();

                return;
            }

            $this->release(30);

            return;
        }

        $target->forceFill([
            'status' => SocialTargetStatus::Published,
            'external_post_id' => $result->externalId,
            'permalink' => $result->permalink,
            'published_at' => now(),
            'last_error' => null,
        ])->save();

        Audit::record('social.published', $target->post, meta: ['account' => $target->account->displayName(), 'external_id' => $result->externalId]);
        $target->post->refreshStatus();
    }
}
