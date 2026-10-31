<?php

use App\Enums\Role;
use App\Enums\SiteKey;
use App\Enums\SocialProvider;
use App\Enums\SocialTargetStatus;
use App\Jobs\CheckSocialPublishStatus;
use App\Jobs\PublishSocialTarget;
use App\Models\MediaAsset;
use App\Models\Site;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Social\MetaDriver;
use App\Social\SocialManager;
use App\Social\TikTokDriver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedCms();
    Storage::fake('public');
    config([
        'social.driver' => 'live',
        'social.meta.app_id' => 'app', 'social.meta.app_secret' => 'secret',
        'social.tiktok.client_key' => 'key', 'social.tiktok.client_secret' => 'secret',
    ]);
    $this->site = Site::findByKey(SiteKey::Pondok);
});

function liveTarget(Site $site, SocialProvider $provider, array $options = []): SocialPostTarget
{
    $account = SocialAccount::query()->create([
        'site_id' => $site->id, 'provider' => $provider, 'external_id' => '1789', 'name' => 'Akun', 'access_token' => 'token',
    ]);
    $asset = MediaAsset::query()->create(['title' => 'Foto']);
    $asset->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('file');
    $post = SocialPost::query()->create(['site_id' => $site->id, 'caption' => 'Halo', 'media' => [$asset->id]]);

    return $post->targets()->create(['social_account_id' => $account->id, 'options' => $options ?: null]);
}

test('meta login connects pages and their instagram accounts', function () {
    Http::fake([
        'graph.facebook.com/*/oauth/access_token*' => Http::sequence()->push(['access_token' => 'short'])->push(['access_token' => 'long']),
        'graph.facebook.com/*/me?*' => Http::response(['id' => 'fb-user', 'name' => 'Admin']),
        'graph.facebook.com/*/me/accounts*' => Http::response(['data' => [[
            'id' => 'page-1', 'name' => 'Pondok Page', 'access_token' => 'page-token',
            'instagram_business_account' => ['id' => 'ig-1', 'username' => 'pondok'],
        ]]]),
    ]);

    $accounts = (new MetaDriver)->connect('code', 'verifier', $this->site, cmsUser(Role::SocialMediaManager));

    expect(collect($accounts)->map(fn ($account) => $account->provider->value)->all())->toBe(['facebook', 'instagram'])
        ->and($accounts[1]->access_token)->toBe('page-token')
        ->and($accounts[1]->provider_user_id)->toBe('fb-user');
});

test('instagram publishes through a media container', function () {
    Http::fake([
        'graph.facebook.com/*/1789/media_publish' => Http::response(['id' => 'ig-media-1']),
        'graph.facebook.com/*/1789/media' => Http::response(['id' => 'container-1']),
        'graph.facebook.com/*/ig-media-1*' => Http::response(['permalink' => 'https://instagram.com/p/abc']),
    ]);
    $target = liveTarget($this->site, SocialProvider::Instagram);

    (new PublishSocialTarget($target))->handle(app(SocialManager::class));

    expect($target->fresh())
        ->status->toBe(SocialTargetStatus::Published)
        ->permalink->toBe('https://instagram.com/p/abc');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/media') && $request['caption'] === 'Halo' && isset($request['image_url']));
});

test('an invalid meta token marks the account for reconnection', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Session expired', 'code' => 190]], 400)]);
    $target = liveTarget($this->site, SocialProvider::Facebook);

    (new PublishSocialTarget($target))->handle(app(SocialManager::class));

    expect($target->fresh()->status)->toBe(SocialTargetStatus::Failed)
        ->and($target->account->fresh()->status)->toBe(SocialAccount::EXPIRED);
});

test('tiktok direct post is checked until it completes', function () {
    Queue::fake();
    Http::fake([
        'open.tiktokapis.com/v2/post/publish/content/init/' => Http::response(['data' => ['publish_id' => 'pub-1'], 'error' => ['code' => 'ok']]),
        'open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response(['data' => ['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => ['7300']], 'error' => ['code' => 'ok']]),
    ]);
    $target = liveTarget($this->site, SocialProvider::TikTok, ['privacy_level' => 'SELF_ONLY', 'disable_comment' => true]);

    (new PublishSocialTarget($target))->handle(app(SocialManager::class));

    expect($target->fresh()->status)->toBe(SocialTargetStatus::Queued)->and($target->fresh()->external_post_id)->toBe('pub-1');
    Queue::assertPushed(CheckSocialPublishStatus::class);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'content/init') && $request['post_info']['privacy_level'] === 'SELF_ONLY' && $request['media_type'] === 'PHOTO');

    (new CheckSocialPublishStatus($target->fresh()))->handle(app(SocialManager::class));

    expect($target->fresh())
        ->status->toBe(SocialTargetStatus::Published)
        ->permalink->toBe('https://www.tiktok.com/video/7300');
});

test('tiktok authorization uses pkce', function () {
    $url = (new TikTokDriver)->authorizationUrl('state123', 'verifier');

    expect($url)->toContain('code_challenge=')->toContain('code_challenge_method=S256')->toContain('state=state123');
});

test('tiktok tokens are refreshed before they expire', function () {
    Http::fake(['open.tiktokapis.com/v2/oauth/token/' => Http::response(['access_token' => 'new', 'refresh_token' => 'r2', 'expires_in' => 86400, 'refresh_expires_in' => 31536000])]);
    $account = SocialAccount::query()->create([
        'site_id' => $this->site->id, 'provider' => SocialProvider::TikTok, 'external_id' => 'open-1', 'name' => 'TT',
        'access_token' => 'old', 'refresh_token' => 'r1', 'token_expires_at' => now()->addHour(),
    ]);

    $this->artisan('social:refresh-tokens')->assertSuccessful();

    expect($account->fresh()->access_token)->toBe('new')->and($account->fresh()->token_expires_at->isAfter(now()->addHours(20)))->toBeTrue();
});
