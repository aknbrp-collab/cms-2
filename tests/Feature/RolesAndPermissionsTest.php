<?php

use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\Site;
use App\Support\Permissions;
use Spatie\Permission\Models\Permission;

beforeEach(fn () => seedCms());

test('every role exists and every granted permission is registered', function () {
    foreach (Role::cases() as $role) {
        expect(array_diff($role->permissions(), Permissions::all()))->toBe([]);
    }

    expect(Spatie\Permission\Models\Role::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(Role::cases())->pluck('value')->sort()->values()->all());
});

test('the permission matrix matches the requirements', function (Role $role, array $allowed, array $denied) {
    $user = cmsUser($role);

    foreach ($allowed as $ability) {
        expect($user->can($ability))->toBeTrue("{$role->value} should be able to {$ability}");
    }

    foreach ($denied as $ability) {
        expect($user->can($ability))->toBeFalse("{$role->value} should not be able to {$ability}");
    }
})->with([
    'superadmin' => [Role::Superadmin, ['users.create', 'roles.assign', 'posts.publish', 'social_posts.publish', 'unregistered.ability'], []],
    'managing editor' => [Role::ManagingEditor, ['posts.publish', 'pages.publish', 'publications.publish', 'siakad_articles.publish'], ['users.create', 'social_posts.create']],
    'pondok admin' => [Role::PondokAdmin, ['pages.create', 'posts.update', 'galleries.delete', 'posts.submit'], ['posts.publish', 'lecturers.create', 'users.view']],
    'mahad admin' => [Role::MahadAdmin, ['pages.create', 'publications.create', 'lecturers.update'], ['posts.publish', 'facilities.create', 'legal_questions.moderate']],
    'social media manager' => [Role::SocialMediaManager, ['social_accounts.connect', 'social_posts.publish'], ['posts.create', 'users.view']],
    'tanya hukum admin' => [Role::TanyaHukumAdmin, ['legal_questions.moderate', 'legal_questions.forward', 'legal_questions.publish'], ['posts.create', 'social_posts.create']],
]);

test('site scoped roles only reach their own site', function () {
    $pondok = Site::findByKey(SiteKey::Pondok);
    $mahad = Site::findByKey(SiteKey::Mahad);

    $pondokAdmin = cmsUser(Role::PondokAdmin);
    $mahadAdmin = cmsUser(Role::MahadAdmin);
    $editor = cmsUser(Role::ManagingEditor);

    expect($pondokAdmin->canAccessSite($pondok))->toBeTrue()
        ->and($pondokAdmin->canAccessSite($mahad))->toBeFalse()
        ->and($pondokAdmin->canAccessAllSites())->toBeFalse()
        ->and($mahadAdmin->canAccessSite($mahad))->toBeTrue()
        ->and($mahadAdmin->canAccessSite($pondok))->toBeFalse()
        ->and($editor->canAccessAllSites())->toBeTrue();
});

test('combining site roles grants both sites', function () {
    $user = cmsUser(Role::PondokAdmin)->assignRole(Role::MahadAdmin->value);

    expect($user->canAccessAllSites())->toBeTrue();
});

test('the seeder is idempotent', function () {
    seedCms();

    expect(Permission::query()->count())->toBe(count(Permissions::all()));
});
