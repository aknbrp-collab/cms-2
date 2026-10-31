<?php

namespace App\Social;

use App\Enums\SocialProvider;
use App\Models\Site;
use App\Models\SocialAccount;
use App\Models\SocialPostTarget;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Pretend platform for development and demos (SOCIAL_DRIVER=fake).
 * A caption containing "#gagal" fails, to try the failure handling.
 */
class FakeDriver implements SocialDriver
{
    public function __construct(protected SocialProvider $provider) {}

    public function providers(): array
    {
        return [$this->provider];
    }

    public function authorizationUrl(string $state, string $codeVerifier): string
    {
        return route('social.callback', $this->provider->oauthApp()).'?'.http_build_query([
            'code' => 'fake-'.$this->provider->value,
            'state' => $state,
            'provider' => $this->provider->value,
        ]);
    }

    public function connect(string $code, string $codeVerifier, Site $site, User $user): array
    {
        $account = SocialAccount::query()->withoutGlobalScopes()->firstOrNew([
            'site_id' => $site->id, 'provider' => $this->provider->value, 'external_id' => 'demo-'.$this->provider->value.'-'.$site->key->value,
        ]);

        $account->fill([
            'name' => $this->provider->label().' Demo '.$site->name,
            'handle' => Str::slug($site->key->value.'-demo'),
            'access_token' => 'fake-token',
            'connected_by' => $user->id,
            'token_expires_at' => $this->provider === SocialProvider::TikTok ? now()->addDay() : null,
            'refresh_token' => $this->provider === SocialProvider::TikTok ? 'fake-refresh' : null,
            'meta' => ['demo' => true],
        ])->forceFill(['status' => SocialAccount::ACTIVE, 'last_error' => null])->save();

        return [$account];
    }

    public function publish(SocialPostTarget $target): PublishResult
    {
        if (str_contains($target->finalCaption(), '#gagal')) {
            throw new SocialException("{$this->provider->label()} (demo): simulated failure.");
        }

        $id = 'demo-'.Str::lower(Str::random(10));

        return PublishResult::done($id, null);
    }

    public function checkStatus(SocialPostTarget $target): PublishResult
    {
        return PublishResult::done((string) $target->external_post_id);
    }

    public function refresh(SocialAccount $account): void
    {
        if ($account->refresh_token !== null) {
            $account->forceFill(['token_expires_at' => now()->addDay()])->save();
        }
    }

    public function creatorInfo(SocialAccount $account): array
    {
        return $this->provider === SocialProvider::TikTok ? [
            'creator_nickname' => $account->name,
            'creator_username' => $account->handle,
            'privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'SELF_ONLY'],
            'comment_disabled' => false,
            'duet_disabled' => false,
            'stitch_disabled' => false,
            'max_video_post_duration_sec' => 600,
        ] : [];
    }
}
