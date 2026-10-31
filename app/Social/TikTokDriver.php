<?php

namespace App\Social;

use App\Enums\SocialProvider;
use App\Models\MediaAsset;
use App\Models\Site;
use App\Models\SocialAccount;
use App\Models\SocialPostTarget;
use App\Models\User;

/**
 * TikTok Login Kit (v2, PKCE) and Content Posting API (Direct Post).
 *
 * Unaudited TikTok apps can only post with privacy "SELF_ONLY"; the
 * composer shows the creator's allowed privacy levels.
 */
class TikTokDriver implements SocialDriver
{
    use HttpHelpers;

    protected const API = 'https://open.tiktokapis.com/v2';

    public function providers(): array
    {
        return [SocialProvider::TikTok];
    }

    public function authorizationUrl(string $state, string $codeVerifier): string
    {
        return 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query([
            'client_key' => config('social.tiktok.client_key'),
            'scope' => implode(',', (array) config('social.tiktok.scopes')),
            'response_type' => 'code',
            'redirect_uri' => route('social.callback', 'tiktok'),
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);
    }

    public function connect(string $code, string $codeVerifier, Site $site, User $user): array
    {
        $token = $this->call(fn ($http) => $http->asForm()->post(self::API.'/oauth/token/', [
            'client_key' => config('social.tiktok.client_key'),
            'client_secret' => config('social.tiktok.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('social.callback', 'tiktok'),
            'code_verifier' => $codeVerifier,
        ]), 'TikTok');

        $profile = $this->call(fn ($http) => $http->withToken((string) $token['access_token'])
            ->get(self::API.'/user/info/', ['fields' => 'open_id,avatar_url,display_name']), 'TikTok');
        $user_info = (array) ($profile['data']['user'] ?? []);

        $account = SocialAccount::query()->withoutGlobalScopes()->firstOrNew([
            'site_id' => $site->id, 'provider' => SocialProvider::TikTok->value, 'external_id' => (string) $token['open_id'],
        ]);

        $account->fill([
            'provider_user_id' => (string) $token['open_id'],
            'name' => (string) ($user_info['display_name'] ?? 'TikTok'),
            'avatar_url' => $user_info['avatar_url'] ?? null,
            'connected_by' => $user->id,
            'scopes' => explode(',', (string) ($token['scope'] ?? '')),
            ...$this->tokenAttributes($token),
        ])->forceFill(['status' => SocialAccount::ACTIVE, 'last_error' => null])->save();

        return [$account];
    }

    public function creatorInfo(SocialAccount $account): array
    {
        $json = $this->call(fn ($http) => $http->withToken($account->access_token)
            ->post(self::API.'/post/publish/creator_info/query/'), 'TikTok');

        return (array) ($json['data'] ?? []);
    }

    public function publish(SocialPostTarget $target): PublishResult
    {
        $account = $target->account;
        $options = $target->options ?? [];
        $media = $target->post->mediaAssets()->values();
        $video = $media->first(fn (MediaAsset $asset) => str_starts_with((string) $asset->file()?->mime_type, 'video/'));
        $images = $media->filter(fn (MediaAsset $asset) => $asset->isImage())->take(35)->values();

        if (empty($options['privacy_level'])) {
            throw new SocialException('TikTok: choose who can view the post (privacy level).');
        }

        $postInfo = [
            'privacy_level' => $options['privacy_level'],
            'disable_comment' => (bool) ($options['disable_comment'] ?? false),
            'brand_content_toggle' => (bool) ($options['brand_content'] ?? false),
            'brand_organic_toggle' => (bool) ($options['brand_organic'] ?? false),
        ];

        if ($video) {
            $json = $this->call(fn ($http) => $http->withToken($account->access_token)->post(self::API.'/post/publish/video/init/', [
                'post_info' => [
                    ...$postInfo,
                    'title' => mb_substr($target->finalCaption(), 0, 2200),
                    'disable_duet' => (bool) ($options['disable_duet'] ?? false),
                    'disable_stitch' => (bool) ($options['disable_stitch'] ?? false),
                ],
                'source_info' => ['source' => 'PULL_FROM_URL', 'video_url' => $video->url()],
            ]), 'TikTok');
        } elseif ($images->isNotEmpty()) {
            $json = $this->call(fn ($http) => $http->withToken($account->access_token)->post(self::API.'/post/publish/content/init/', [
                'post_info' => [...$postInfo, 'title' => mb_substr($target->post->caption, 0, 90), 'description' => mb_substr($target->finalCaption(), 0, 4000)],
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                    'photo_cover_index' => 0,
                    'photo_images' => $images->map(fn (MediaAsset $asset) => $asset->url())->all(),
                ],
                'post_mode' => 'DIRECT_POST',
                'media_type' => 'PHOTO',
            ]), 'TikTok');
        } else {
            throw new SocialException('TikTok: a post needs a video or at least one photo.');
        }

        return PublishResult::pending((string) ($json['data']['publish_id'] ?? ''));
    }

    public function checkStatus(SocialPostTarget $target): PublishResult
    {
        $json = $this->call(fn ($http) => $http->withToken($target->account->access_token)
            ->post(self::API.'/post/publish/status/fetch/', ['publish_id' => $target->external_post_id]), 'TikTok');

        $data = (array) ($json['data'] ?? []);

        return match ($data['status'] ?? null) {
            'PUBLISH_COMPLETE' => PublishResult::done(
                (string) (($data['publicaly_available_post_id'] ?? [])[0] ?? $target->external_post_id),
                isset(($data['publicaly_available_post_id'] ?? [])[0]) ? 'https://www.tiktok.com/video/'.$data['publicaly_available_post_id'][0] : null,
            ),
            'FAILED' => throw new SocialException('TikTok: '.($data['fail_reason'] ?? 'publishing failed')),
            default => PublishResult::pending((string) $target->external_post_id),
        };
    }

    public function refresh(SocialAccount $account): void
    {
        if ($account->refresh_token === null || ($account->token_expires_at?->isAfter(now()->addHours(6)) ?? false)) {
            return;
        }

        $token = $this->call(fn ($http) => $http->asForm()->post(self::API.'/oauth/token/', [
            'client_key' => config('social.tiktok.client_key'),
            'client_secret' => config('social.tiktok.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ]), 'TikTok');

        $account->fill($this->tokenAttributes($token))->save();
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    protected function tokenAttributes(array $token): array
    {
        return [
            'access_token' => (string) $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
            'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 86400)),
            'refresh_expires_at' => isset($token['refresh_expires_in']) ? now()->addSeconds((int) $token['refresh_expires_in']) : null,
        ];
    }
}
