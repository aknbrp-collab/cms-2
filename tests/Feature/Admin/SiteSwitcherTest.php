<?php

use App\Enums\Role;
use App\Enums\SiteKey;
use App\Http\Middleware\SetAdminSite;
use App\Models\Site;

beforeEach(function () {
    seedCms();

    $this->pondok = Site::findByKey(SiteKey::Pondok);
    $this->mahad = Site::findByKey(SiteKey::Mahad);
});

test('site scoped admins default to their own site', function () {
    $this->actingAs(cmsUser(Role::MahadAdmin))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSessionHas(SetAdminSite::SESSION_KEY, $this->mahad->id);
});

test('cross-site roles default to all sites', function () {
    $this->actingAs(cmsUser(Role::ManagingEditor))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSessionHas(SetAdminSite::SESSION_KEY, SetAdminSite::ALL);
});

test('users can switch to a site they manage', function () {
    $this->actingAs(cmsUser(Role::ManagingEditor))
        ->from(route('dashboard'))
        ->post(route('admin.switch-site'), ['site' => $this->pondok->id])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas(SetAdminSite::SESSION_KEY, $this->pondok->id);

    $this->get(route('dashboard'))->assertSee($this->pondok->name);
});

test('users cannot switch to a site they do not manage', function () {
    $this->actingAs(cmsUser(Role::PondokAdmin))
        ->post(route('admin.switch-site'), ['site' => $this->mahad->id])
        ->assertForbidden();
});

test('site scoped admins cannot switch to all sites', function () {
    $this->actingAs(cmsUser(Role::PondokAdmin))
        ->post(route('admin.switch-site'), ['site' => SetAdminSite::ALL])
        ->assertForbidden();
});

test('a tampered session falls back to an allowed site', function () {
    $this->actingAs(cmsUser(Role::PondokAdmin))
        ->withSession([SetAdminSite::SESSION_KEY => $this->mahad->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSessionHas(SetAdminSite::SESSION_KEY, $this->pondok->id);
});

test('invalid site ids are rejected', function () {
    $this->actingAs(cmsUser(Role::ManagingEditor))
        ->post(route('admin.switch-site'), ['site' => 'not-a-uuid'])
        ->assertNotFound();
});
