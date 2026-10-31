<?php

use App\Enums\Role;
use App\Enums\SiteKey;
use App\Http\Middleware\SetAdminSite;
use App\Models\AdmissionPeriod;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;

beforeEach(fn () => seedCms());

test('every admin screen renders for a superadmin on a site', function (string $route, ?string $model) {
    $site = Site::findByKey(SiteKey::Pondok);
    $parameter = $model ? $model::factory()->for($site)->create() : null;

    $this->actingAs(cmsUser(Role::Superadmin))
        ->withSession([SetAdminSite::SESSION_KEY => $site->id])
        ->get(route($route, $parameter))
        ->assertOk();
})->with([
    ['dashboard', null],
    ['admin.posts.index', null],
    ['admin.posts.create', null],
    ['admin.posts.edit', Post::class],
    ['admin.pages.index', null],
    ['admin.pages.edit', Page::class],
    ['admin.events.index', null],
    ['admin.events.create', null],
    ['admin.events.edit', Event::class],
    ['admin.galleries.index', null],
    ['admin.galleries.create', null],
    ['admin.galleries.edit', Gallery::class],
    ['admin.faqs', null],
    ['admin.leaders', null],
    ['admin.admissions.index', null],
    ['admin.admissions.create', null],
    ['admin.admissions.edit', AdmissionPeriod::class],
    ['admin.admissions.registrations', null],
    ['admin.categories', null],
    ['admin.media', null],
    ['admin.menus', null],
    ['admin.site-settings', null],
    ['admin.review', null],
    ['admin.users.index', null],
    ['admin.roles', null],
    ['admin.audit-logs', null],
]);

test('module screens are forbidden without the module permission', function () {
    $this->actingAs(cmsUser(Role::SocialMediaManager));

    $this->get(route('admin.events.index'))->assertForbidden();
    $this->get(route('admin.faqs'))->assertForbidden();
    $this->get(route('admin.admissions.registrations'))->assertForbidden();
});
