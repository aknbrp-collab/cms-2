<?php

namespace App\Social;

use App\Enums\SocialProvider;
use App\Models\MediaAsset;
use App\Models\Site;
use App\Models\SocialAccount;
use App\Models\SocialPostTarget;
use App\Models\User;

/**
 * Meta Graph API: one login connects the user's Facebook Pages and the
 * Instagram Business accounts linked to them. Page tokens obtained from a
 * long-lived user token do not expire.
 */
class MetaDriver implements SocialDriver
{
    use HttpHelpers;

    public function providers(): array
    {
        return [SocialProvider::Facebook, SocialProvider::Instagram];
    }

    public function authorizationUrl(string $state, string $codeVerifier): string
    {
        return 'https://www.facebook.com/'.$this->version().'/dialog/oauth?'.http_build_query([
            'client_id' => config('social.meta.app_id'),
            'redirect_uri' => route('social.callback', 'meta'),
            'state' => $state,
            'response_type' => 'code',
            'scope' => implode(',', (array) config('social.meta.scopes')),
        ]);
    }

    public function connect(string $code, string $codeVerifier, Site $site, User $user): array
    {
        $short = $this->call(fn ($http) => $http->get($this->graph('oauth/access_token'), [
            'client_id' => config('social.meta.app_id'),
            'client_secret' => config('social.meta.app_secret'),
            'redirect_uri' => route('social.callback', 'meta'),
            'code' => $code,
        ]), 'Meta');

        $long = $this->call(fn ($http) => $http->get($this->graph('oauth/access_token'), [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('social.meta.app_id'),
            'client_secret' => config('social.meta.app_secret'),
            'fb_exchange_token' => $short['access_token'] ?? '',
        ]), 'Meta');

        $userToken = (string) ($long['access_token'] ?? '');
        $me = $this->call(fn ($http) => $http->get($this->graph('me'), ['fields' => 'id,name', 'access_token' => $userToken]), 'Meta');
        $pages = $this->call(fn ($http) => $http->get($this->graph('me/accounts'), [
            'fields' => 'id,name,access_token,picture{url},instagram_business_account{id,username,profile_picture_url}',
            'access_token' => $userToken,
        ]), 'Meta');

        $accounts = [];

        foreach ((array) ($pages['data'] ?? []) as $page) {
            $accounts[] = $this->store($site, $user, SocialProvider::Facebook, (string) $page['id'], [
                'provider_user_id' => (string) ($me['id'] ?? ''),
                'name' => (string) $page['name'],
                'avatar_url' => $page['picture']['data']['url'] ?? null,
                'access_token' => (string) $page['access_token'],
                'scopes' => config('social.meta.scopes'),
                'meta' => ['page_id' => (string) $page['id']],
            ]);

            if ($instagram = $page['instagram_business_account'] ?? null) {
                $accounts[] = $this->store($site, $user, SocialProvider::Instagram, (string) $instagram['id'], [
                    'provider_user_id' => (string) ($me['id'] ?? ''),
                    'name' => (string) ($instagram['username'] ?? $page['name']),
                    'handle' => $instagram['username'] ?? null,
                    'avatar_url' => $instagram['profile_picture_url'] ?? null,
                    'access_token' => (string) $page['access_token'],
                    'scopes' => config('social.meta.scopes'),
                    'meta' => ['page_id' => (string) $page['id']],
                ]);
            }
        }

        if ($accounts === []) {
            throw new SocialException('Meta: no Facebook Page was granted. Choose at least one Page during login.');
        }

        return $accounts;
    }

    public function publish(SocialPostTarget $target): PublishResult
    {
        return $target->provider() === SocialProvider::Instagram
            ? $this->publishInstagram($target)
            : $this->publishFacebook($target);
    }

    public function checkStatus(SocialPostTarget $target): PublishResult
    {
        return PublishResult::done((string) $target->external_post_id, $target->permalink);
    }

    public function refresh(SocialAccount $account): void
    {
        // Page tokens from a long-lived user token do not expire; nothing to do.
    }

    public function creatorInfo(SocialAccount $account): array
    {
        return [];
    }

    protected function publishFacebook(SocialPostTarget $target): PublishResult
    {
        $account = $target->account;
        $pageId = $account->external_id;
        $token = $account->access_token;
        $caption = $target->finalCaption();
        $media = $target->post->mediaAssets();
        $images = $media->filter(fn (MediaAsset $asset) => $asset->isImage())->values();
        $video = $media->first(fn (MediaAsset $asset) => str_starts_with((string) $asset->file()?->mime_type, 'video/'));

        if ($video) {
            $json = $this->call(fn ($http) => $http->asForm()->post($this->graph("{$pageId}/videos"), [
                'file_url' => $video->url(), 'description' => $caption, 'access_token' => $token,
            ]), 'Facebook');
        } elseif ($images->count() === 1) {
            $json = $this->call(fn ($http) => $http->asForm()->post($this->graph("{$pageId}/photos"), [
                'url' => $images->first()->url(), 'caption' => $caption, 'access_token' => $token,
            ]), 'Facebook');
        } elseif ($images->count() > 1) {
            $attached = [];

            foreach ($images as $index => $image) {
                $photo = $this->call(fn ($http) => $http->asForm()->post($this->graph("{$pageId}/photos"), [
                    'url' => $image->url(), 'published' => 'false', 'access_token' => $token,
                ]), 'Facebook');
                $attached["attached_media[{$index}]"] = json_encode(['media_fbid' => $photo['id']]);
            }

            $json = $this->call(fn ($http) => $http->asForm()->post($this->graph("{$pageId}/feed"), [
                'message' => $caption, 'access_token' => $token, ...$attached,
            ]), 'Facebook');
        } else {
            $json = $this->call(fn ($http) => $http->asForm()->post($this->graph("{$pageId}/feed"), array_filter([
                'message' => $caption, 'link' => $target->post->link, 'access_token' => $token,
            ])), 'Facebook');
        }

        $id = (string) ($json['post_id'] ?? $json['id'] ?? '');

        return PublishResult::done($id, "https://www.facebook.com/{$id}");
    }

    protected function publishInstagram(SocialPostTarget $target): PublishResult
    {
        $account = $target->account;
        $igId = $account->external_id;
        $token = $account->access_token;
        $media = $target->post->mediaAssets()->values();

        if ($media->isEmpty()) {
            throw new SocialException('Instagram: a post needs at least one image or video.');
        }

        $container = function (MediaAsset $asset, array $extra) use ($igId, $token) {
            $isVideo = str_starts_with((string) $asset->file()?->mime_type, 'video/');

            $json = $this->call(fn ($http) => $http->asForm()->post($this->graph("{$igId}/media"), [
                ...($isVideo ? ['media_type' => 'REELS', 'video_url' => $asset->url()] : ['image_url' => $asset->url()]),
                ...$extra,
                'access_token' => $token,
            ]), 'Instagram');

            if ($isVideo) {
                $this->waitForContainer((string) $json['id'], $token);
            }

            return (string) $json['id'];
        };

        if ($media->count() === 1) {
            $creationId = $container($media->first(), ['caption' => $target->finalCaption()]);
        } else {
            $children = $media->take(10)->map(fn (MediaAsset $asset) => $container($asset, ['is_carousel_item' => 'true']))->all();
            $creationId = (string) $this->call(fn ($http) => $http->asForm()->post($this->graph("{$igId}/media"), [
                'media_type' => 'CAROUSEL', 'children' => implode(',', $children), 'caption' => $target->finalCaption(), 'access_token' => $token,
            ]), 'Instagram')['id'];
        }

        $published = $this->call(fn ($http) => $http->asForm()->post($this->graph("{$igId}/media_publish"), [
            'creation_id' => $creationId, 'access_token' => $token,
        ]), 'Instagram');

        $mediaId = (string) $published['id'];
        $details = $this->call(fn ($http) => $http->get($this->graph($mediaId), ['fields' => 'permalink', 'access_token' => $token]), 'Instagram');

        return PublishResult::done($mediaId, $details['permalink'] ?? null);
    }

    /**
     * Videos are processed asynchronously by Instagram before they can be published.
     */
    protected function waitForContainer(string $containerId, string $token): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $status = $this->call(fn ($http) => $http->get($this->graph($containerId), ['fields' => 'status_code', 'access_token' => $token]), 'Instagram');

            match ($status['status_code'] ?? null) {
                'FINISHED' => null,
                'ERROR', 'EXPIRED' => throw new SocialException('Instagram could not process the video.'),
                default => usleep(app()->runningUnitTests() ? 0 : 3_000_000),
            };

            if (($status['status_code'] ?? null) === 'FINISHED') {
                return;
            }
        }

        throw new SocialException('Instagram is still processing the video.', retryable: true);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function store(Site $site, User $user, SocialProvider $provider, string $externalId, array $attributes): SocialAccount
    {
        $account = SocialAccount::query()->withoutGlobalScopes()->firstOrNew([
            'site_id' => $site->id, 'provider' => $provider->value, 'external_id' => $externalId,
        ]);

        $account->fill([...$attributes, 'connected_by' => $user->id, 'token_expires_at' => null])->forceFill(['status' => SocialAccount::ACTIVE, 'last_error' => null])->save();

        return $account;
    }

    protected function graph(string $path): string
    {
        return 'https://graph.facebook.com/'.$this->version().'/'.ltrim($path, '/');
    }

    protected function version(): string
    {
        return (string) config('social.meta.graph_version');
    }
}
