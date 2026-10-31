<?php

use App\Enums\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => seedCms());

test('only users with permission can open the users page', function () {
    $this->actingAs(cmsUser(Role::ManagingEditor))->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs(cmsUser(Role::Superadmin))->get(route('admin.users.index'))->assertOk();
});

test('a superadmin can create a user with roles', function () {
    $this->actingAs(cmsUser(Role::Superadmin));

    Livewire::test('pages::admin.users.index')
        ->call('create')
        ->set('name', 'Admin Pondok')
        ->set('email', 'admin@pondok.test')
        ->set('password', 'secret-password')
        ->set('roles', [Role::PondokAdmin->value])
        ->call('save')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'admin@pondok.test')->sole();

    expect($user->hasRole(Role::PondokAdmin->value))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->is_active)->toBeTrue();
});

test('a superadmin can edit roles and deactivate a user', function () {
    $this->actingAs(cmsUser(Role::Superadmin));
    $user = cmsUser(Role::PondokAdmin);

    Livewire::test('pages::admin.users.index')
        ->call('edit', $user->id)
        ->set('roles', [Role::MahadAdmin->value])
        ->set('isActive', false)
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->getRoleNames()->all())->toBe([Role::MahadAdmin->value])
        ->and($user->is_active)->toBeFalse();
});

test('users cannot deactivate themselves', function () {
    $admin = cmsUser(Role::Superadmin);
    $this->actingAs($admin);

    Livewire::test('pages::admin.users.index')
        ->call('edit', $admin->id)
        ->set('isActive', false)
        ->call('save')
        ->assertHasErrors('isActive');

    expect($admin->refresh()->is_active)->toBeTrue();
});

test('unknown roles are rejected', function () {
    $this->actingAs(cmsUser(Role::Superadmin));

    Livewire::test('pages::admin.users.index')
        ->call('create')
        ->set('name', 'X')
        ->set('email', 'x@pondok.test')
        ->set('password', 'secret-password')
        ->set('roles', ['hacker'])
        ->call('save')
        ->assertHasErrors('roles.0');
});

test('the create superadmin command creates a verified superadmin', function () {
    $this->artisan('cms:create-superadmin', ['--name' => 'Root', '--email' => 'root@pondok.test'])
        ->expectsQuestion('Password', 'secret-password')
        ->assertSuccessful();

    $user = User::query()->where('email', 'root@pondok.test')->sole();

    expect($user->isSuperadmin())->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue();
});
