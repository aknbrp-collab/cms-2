<?php

use App\Enums\Role;
use App\Enums\SiteKey;
use App\Enums\SocialPostStatus;
use App\Enums\SocialProvider;
use App\Enums\SocialTargetStatus;
use App\Http\Middleware\SetAdminSite;
use App\Jobs\PublishSocialTarget;
use App\Models\MediaAsset;
use App\Models\Site;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Social\SocialManager;
use App\Social\SocialPublisher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedCms();
    Storage::fake('public');
    config(['social.driver' => 'fake']);
    $this->site = Site::findByKey(SiteKey::Pondok);
    $this->manager = cmsUser(Role::SocialMediaManager);
});

function demoAccount(Site $site, SocialProvider $provider): SocialAccount
{
    return SocialAccount::query()->create([
        'site_id' => $site->id, 'provider' => $provider, 'external_id' => 'ext-'.$provider->value,
        'name' => $provider->label().' Pondok', 'access_token' => 'secret-token',
    ]);
}

function imageAsset(): MediaAsset
{
    $asset = MediaAsset::query()->create(['title' => 'Foto']);
    $asset->addMedia(UploadedFile::fake()->image('foto.jpg'))->toMediaCollection('file');

    return $asset;
}

function socialPost(Site $site, array $accounts, array $attributes = [], array $options = []): SocialPost
{
    $post = SocialPost::query()->create(['site_id' => $site->id, 'caption' => 'Kegiatan santri hari ini', ...$attributes]);

    foreach ($accounts as $account) {
        $post->targets()->create(['social_account_id' => $account->id, 'options' => $account->provider === SocialProvider::TikTok ? ['privacy_level' => 'SELF_ONLY', ...$options] : null]);
    }

    return $post;
}

test('connecting in demo mode goes through the oauth state check', function () {
    $this->actingAs($this->manager)->withSession([SetAdminSite::SESSION_KEY => $this->site->id]);

    $redirect = $this->get(route('social.connect', 'instagram'))->assertRedirect();
    $this->get($redirect->headers->get('Location'))->assertRedirect(route('admin.social.accounts'));

    $account = SocialAccount::query()->withoutGlobalScopes()->sole();
    expect($account->provider)->toBe(SocialProvider::Instagram)
        ->and($account->site_id)->toBe($this->site->id)
        ->and(DB::table('social_accounts')->value('access_token'))->not->toBe('fake-token');

    $this->get(route('social.callback', 'meta').'?state=forged&code=x')
        ->assertRedirect(route('admin.social.accounts'))
        ->assertSessionHas('error');
});

test('publishing creates one job per account', function () {
    Queue::fake();
    $post = socialPost($this->site, [demoAccount($this->site, SocialProvider::Facebook), demoAccount($this->site, SocialProvider::Instagram)], ['media' => [imageAsset()->id]]);

    app(SocialPublisher::class)->schedule($post, $this->manager);

    expect($post->fresh()->status)->toBe(SocialPostStatus::Publishing);
    Queue::assertPushed(PublishSocialTarget::class, 2);
});

test('a failing account does not stop the others', function () {
    $facebook = demoAccount($this->site, SocialProvider::Facebook);
    $instagram = demoAccount($this->site, SocialProvider::Instagram);
    $post = socialPost($this->site, [$facebook, $instagram], ['media' => [imageAsset()->id]]);
    $post->targets()->where('social_account_id', $instagram->id)->update(['hashtags' => 'gagal']);

    app(SocialPublisher::class)->schedule($post, $this->manager);

    $post->refresh();
    expect($post->status)->toBe(SocialPostStatus::PartiallyFailed)
        ->and($post->targets()->where('social_account_id', $facebook->id)->sole()->status)->toBe(SocialTargetStatus::Published)
        ->and($post->targets()->where('social_account_id', $instagram->id)->sole()->status)->toBe(SocialTargetStatus::Failed);

    $failed = $post->targets()->where('status', 'failed')->sole();
    $failed->update(['hashtags' => null]);
    app(SocialPublisher::class)->retry($failed, $this->manager);

    expect($post->fresh()->status)->toBe(SocialPostStatus::Published);
});

test('scheduled posts are dispatched when due', function () {
    Queue::fake();
    $post = socialPost($this->site, [demoAccount($this->site, SocialProvider::Facebook)]);

    app(SocialPublisher::class)->schedule($post, $this->manager, now()->addHour());
    expect($post->fresh()->status)->toBe(SocialPostStatus::Scheduled);

    $this->artisan('social:dispatch-due')->assertSuccessful();
    Queue::assertNothingPushed();

    $this->travel(61)->minutes();
    $this->artisan('social:dispatch-due')->assertSuccessful();
    Queue::assertPushed(PublishSocialTarget::class, 1);
});

test('platform rules are checked before publishing', function () {
    $instagram = demoAccount($this->site, SocialProvider::Instagram);
    $tiktok = demoAccount($this->site, SocialProvider::TikTok);
    $post = socialPost($this->site, [$instagram, $tiktok], ['caption' => str_repeat('a', 2300), 'media' => [imageAsset()->id]], ['privacy_level' => null]);
    $post->targets()->where('social_account_id', $tiktok->id)->update(['caption_override' => 'Pendek']);

    $problems = app(SocialPublisher::class)->problems($post);

    expect(implode(' ', $problems))
        ->toContain('limit is 2200')
        ->toContain('choose who can view');
});

test('branded tiktok content cannot be private', function () {
    $tiktok = demoAccount($this->site, SocialProvider::TikTok);
    $post = socialPost($this->site, [$tiktok], ['media' => [imageAsset()->id]], ['privacy_level' => 'SELF_ONLY', 'brand_content' => true]);

    expect(implode(' ', app(SocialPublisher::class)->problems($post)))->toContain('branded content cannot be private');
});

test('the calendar reschedules by drag and drop', function () {
    $post = socialPost($this->site, [demoAccount($this->site, SocialProvider::Facebook)]);
    app(SocialPublisher::class)->schedule($post, $this->manager, now()->addDay()->setTime(10, 30));
    $this->actingAs($this->manager);

    $newDay = now()->addDays(5)->toDateString();
    Livewire::test('pages::admin.social.calendar')->call('move', $post->id, $newDay);

    expect($post->fresh()->scheduled_at->toDateString())->toBe($newDay)
        ->and($post->fresh()->scheduled_at->format('H:i'))->toBe('10:30');

    Livewire::test('pages::admin.social.calendar')->call('move', $post->id, now()->subDay()->toDateString());
    expect($post->fresh()->scheduled_at->toDateString())->toBe($newDay);
});

test('the composer saves targets with tiktok options', function () {
    $tiktok = demoAccount($this->site, SocialProvider::TikTok);
    onSite(SiteKey::Pondok);
    $this->actingAs($this->manager);

    Livewire::test('pages::admin.social.compose')
        ->set('caption', 'Video kegiatan')
        ->set('accountIds', [$tiktok->id])
        ->assertSee('Music Usage Confirmation')
        ->set("targets.{$tiktok->id}.privacy_level", 'PUBLIC_TO_EVERYONE')
        ->set("targets.{$tiktok->id}.allow_comment", true)
        ->set("targets.{$tiktok->id}.hashtags", 'santri, #pesantren')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $target = SocialPost::query()->sole()->targets()->sole();
    expect($target->options)->toMatchArray(['privacy_level' => 'PUBLIC_TO_EVERYONE', 'disable_comment' => false, 'disable_duet' => true])
        ->and($target->load('post')->finalCaption())->toEndWith('#santri #pesantren');
});

test('social screens follow permissions', function () {
    $this->actingAs($this->manager)->withSession([SetAdminSite::SESSION_KEY => $this->site->id]);

    foreach (['admin.social.accounts', 'admin.social.posts', 'admin.social.calendar', 'admin.social.create'] as $route) {
        $this->get(route($route))->assertOk();
    }

    $this->actingAs(cmsUser(Role::PondokAdmin))->get(route('admin.social.posts'))->assertForbidden();
});

test('live mode refuses providers without app keys', function () {
    config(['social.driver' => 'live', 'social.meta.app_id' => null]);

    expect(app(SocialManager::class)->canConnect(SocialProvider::Facebook))->toBeFalse();
    $this->actingAs($this->manager)->withSession([SetAdminSite::SESSION_KEY => $this->site->id])
        ->get(route('social.connect', 'facebook'))
        ->assertRedirect(route('admin.social.accounts'))
        ->assertSessionHas('error');
});
