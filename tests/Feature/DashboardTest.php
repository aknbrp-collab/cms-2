<?php

use App\Enums\Role;
use App\Models\User;

beforeEach(fn () => seedCms());

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('users with a cms role can visit the dashboard', function () {
    $this->actingAs(cmsUser(Role::PondokAdmin));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Pondok Web Admin');
});

test('users without a cms role are forbidden', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertForbidden();
});

test('inactive users are logged out', function () {
    $this->actingAs(cmsUser(Role::ManagingEditor, ['is_active' => false]));

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('admin routes are only served on the admin domain', function () {
    $this->actingAs(cmsUser(Role::Superadmin));

    $this->get('http://pondok.test/dashboard')->assertNotFound();
    $this->get(adminUrl('dashboard'))->assertOk();
});
