<?php

use App\Enums\Role;
use App\Enums\SiteKey;
use App\Enums\SocialProvider;
use App\Http\Middleware\SetAdminSite;
use App\Models\AdmissionPeriod;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\SocialAccount;
use Livewire\Livewire;

beforeEach(fn () => seedCms());

test('site admins cannot open another site\'s records by url', function (string $route, string $model) {
    $record = $model::factory()->for(Site::findByKey(SiteKey::Pondok))->create();
    $mahad = Site::findByKey(SiteKey::Mahad);

    $response = $this->actingAs(cmsUser(Role::MahadAdmin))
        ->withSession([SetAdminSite::SESSION_KEY => $mahad->id])
        ->get(route($route, $record));

    expect($response->status())->toBeIn([403, 404]);
})->with([
    ['admin.posts.edit', Post::class],
    ['admin.pages.edit', Page::class],
    ['admin.events.edit', Event::class],
    ['admin.galleries.edit', Gallery::class],
    ['admin.admissions.edit', AdmissionPeriod::class],
]);

test('switching the session to another site does not help', function () {
    $post = Post::factory()->for(Site::findByKey(SiteKey::Pondok))->create();

    $response = $this->actingAs(cmsUser(Role::MahadAdmin))
        ->withSession([SetAdminSite::SESSION_KEY => Site::findByKey(SiteKey::Pondok)->id])
        ->get(route('admin.posts.edit', $post));

    expect($response->status())->toBeIn([403, 404]);
});

test('new content needs a site even for superadmins', function (string $component) {
    $this->actingAs(cmsUser(Role::Superadmin));

    Livewire::test($component)->assertForbidden();
})->with([
    'pages::admin.posts.edit',
    'pages::admin.pages.edit',
    'pages::admin.events.edit',
    'pages::admin.galleries.edit',
    'pages::admin.publications.edit',
]);

test('social posts cannot target accounts of another site', function () {
    $mahadAccount = SocialAccount::query()->create([
        'site_id' => Site::findByKey(SiteKey::Mahad)->id, 'provider' => SocialProvider::Facebook,
        'external_id' => 'mahad-page', 'name' => 'Mahad Page', 'access_token' => 't',
    ]);
    onSite(SiteKey::Pondok);
    $this->actingAs(cmsUser(Role::SocialMediaManager));

    Livewire::test('pages::admin.social.compose')
        ->set('caption', 'Halo')
        ->set('accountIds', [$mahadAccount->id])
        ->call('saveDraft')
        ->assertHasErrors('accountIds.0');
});
