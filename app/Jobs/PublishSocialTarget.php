<?php

namespace App\Jobs;

use App\Enums\SocialTargetStatus;
use App\Models\SocialAccount;
use App\Models\SocialPostTarget;
use App\Social\SocialException;
use App\Social\SocialManager;
use App\Support\Audit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Publish a post to one account. Each target is its own job, so a failure
 * on one account never blocks the others.
 */
class PublishSocialTarget implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Video uploads and Instagram container processing can take minutes.
     * Keep the queue's retry_after above this value.
     */
    public int $timeout = 300;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public SocialPostTarget $target) {}

    /**
     * One publish at a time per account (platform rate limits).
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->target->social_account_id))->releaseAfter(30)->expireAfter(300)];
    }

    public function handle(SocialManager $social): void
    {
        $target = $this->target->fresh(['account', 'post']) ?? $this->target;

        if ($target->status === SocialTargetStatus::Published) {
            return;
        }

        $account = $target->account;

        if (! $account->isUsable()) {
            $this->fail($target, __('The :provider account needs to be reconnected.', ['provider' => $account->provider->label()]));

            return;
        }

        $target->forceFill(['attempts' => $target->attempts + 1])->save();

        try {
            $result = $social->forProvider($account->provider)->publish($target);
        } catch (SocialException $e) {
            if ($e->tokenInvalid) {
                $account->forceFill(['status' => SocialAccount::EXPIRED, 'last_error' => $e->getMessage()])->save();
            }

            if ($e->retryable && $this->attempts() < $this->tries) {
                $target->forceFill(['last_error' => $e->getMessage()])->save();

                throw $e;
            }

            $this->fail($target, $e->getMessage());

            return;
        }

        if ($result->pending) {
            $target->forceFill(['status' => SocialTargetStatus::Queued, 'external_post_id' => $result->externalId, 'last_error' => null])->save();
            CheckSocialPublishStatus::dispatch($target)->delay(now()->addSeconds(30));

            return;
        }

        $target->forceFill([
            'status' => SocialTargetStatus::Published,
            'external_post_id' => $result->externalId,
            'permalink' => $result->permalink,
            'published_at' => now(),
            'last_error' => null,
        ])->save();

        Audit::record('social.published', $target->post, meta: ['account' => $account->displayName(), 'external_id' => $result->externalId]);
        $target->post->refreshStatus();
    }

    public function failed(?Throwable $exception): void
    {
        $this->fail($this->target->fresh(['post', 'account']) ?? $this->target, (string) $exception?->getMessage());
    }

    protected function fail(SocialPostTarget $target, string $error): void
    {
        $target->forceFill(['status' => SocialTargetStatus::Failed, 'last_error' => $error])->save();

        Audit::record('social.failed', $target->post, meta: ['account' => $target->account->displayName(), 'error' => $error]);
        $target->post->refreshStatus();
    }
}
